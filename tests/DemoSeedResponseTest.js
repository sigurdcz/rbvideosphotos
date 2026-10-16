const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(require('node:path').join(__dirname, '../admin/views/setup/index.php'), 'utf8');
const start = source.indexOf('  const readPayload = async response => {');
const end = source.indexOf('  const post = async', start);
assert(start >= 0 && end > start, 'Actual response parser must exist');
const parse = vm.runInNewContext(source.slice(start, end) + '\nreadPayload;', {
  URL, window: {location: {href: 'https://example.invalid/admin/setup'}}
});
const response = (body, extra = {}) => ({status: 200, ok: true, redirected: false, text: async () => body, ...extra});
(async () => {
  const valid = await parse(response('{"success":true,"job":{"status":"running"}}'));
  assert.equal(valid.job.status, 'running');
  await assert.rejects(parse(response('<br>Deprecated: GD warning<br>{"success":true}')), /platný JSON.*HTTP 200/);
  await assert.rejects(parse(response('{"success":false,"error":"Media processing failed"}', {status: 500, ok: false})), /Media processing failed/);
  await assert.rejects(parse(response('<html>Login</html>', {redirected: true, url: 'https://example.invalid/admin/login'})), /Přihlášení vypršelo/);
  console.log('PASS valid JSON, polluted HTTP 200, server error and expired session responses');
})().catch(error => { console.error(error); process.exitCode = 1; });
