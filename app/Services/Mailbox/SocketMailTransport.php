<?php
namespace App\Services\Mailbox;

final class SocketMailTransport implements MailTransport
{
    /** @var resource */
    private $stream;

    public function __construct(string $host, int $port, bool $implicitTls = true, int $timeout = 20)
    {
        $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false]]);
        $scheme = $implicitTls ? 'ssl' : 'tcp';
        $stream = @stream_socket_client($scheme . '://' . $host . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
        if (!is_resource($stream)) {
            throw new ImapException('imap_connect');
        }
        stream_set_timeout($stream, $timeout);
        $this->stream = $stream;
    }

    public function readLine(): ?string
    {
        $line = fgets($this->stream, 65536);
        if ($line === false) {
            return null;
        }
        return rtrim($line, "\r\n");
    }

    public function readBytes(int $length): ?string
    {
        $data = '';
        while (strlen($data) < $length) {
            $chunk = fread($this->stream, min(65536, $length - strlen($data)));
            if ($chunk === false || $chunk === '') {
                return null;
            }
            $data .= $chunk;
        }
        return $data;
    }

    public function write(string $data): void
    {
        $length = strlen($data);
        $written = 0;
        while ($written < $length) {
            $n = fwrite($this->stream, substr($data, $written));
            if ($n === false || $n === 0) {
                throw new ImapException('imap_write');
            }
            $written += $n;
        }
    }

    public function close(): void
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
    }
}
