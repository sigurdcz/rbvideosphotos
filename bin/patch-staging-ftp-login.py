"""Apply the reviewed pre-login UTF8 compatibility change to the pinned action.

The hosting closes a TLS connection on OPTS UTF8 before USER. basic-ftp already
sets UTF8 again in useDefaultSettings after login. Retain that command and all
sync logic, TLS settings, credentials and exclusions. Fail if upstream differs.
"""
import os
from pathlib import Path

REVISION = "110f9186c050f71550953127052e77650219c287"
target = (Path(os.environ["RUNNER_WORKSPACE"]).parent / "_actions" /
          "SamKirkland" / "FTP-Deploy-Action" / REVISION / "dist" / "index.js")
before = (b'await this.sendIgnoringError("OPTS UTF8 ON");\n'
          b'        await this.login(options.user, options.password);')
after = (b'// Hosting compatibility: UTF8 is set by useDefaultSettings after login.\n'
         b'        await this.login(options.user, options.password);')
source = target.read_bytes()
if source.count(before) != 1:
    raise RuntimeError("Pinned FTP action changed: expected exactly one pre-login UTF8 call.")
if source.count(b'await this.sendIgnoringError("OPTS UTF8 ON");') != 2:
    raise RuntimeError("Expected the separate post-login UTF8 initialization to remain present.")
target.write_bytes(source.replace(before, after, 1))
print("Applied staging-only login ordering compatibility patch; post-login UTF8 retained.")
