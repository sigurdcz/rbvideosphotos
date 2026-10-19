<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Services\SmtpMailer;
use App\Services\SmtpTransportException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

final class TestSmtpChannel extends SMTP
{
    public array $events = [];
    public bool $open = false;
    public bool $authAccepted = true;
    public bool $dataAccepted = true;
    public function connect($host, $port = null, $timeout = 30, $options = []) { $this->events['connect'] = [$host, $port, $options]; $this->open = true; return true; }
    public function connected() { return $this->open; }
    public function hello($host = '') { return true; }
    public function startTLS() { $this->events['starttls'] = true; return true; }
    public function authenticate($username, $password, $authtype = null, $OAuth = null) { $this->events['auth'] = [$username, $password]; return $this->authAccepted; }
    public function getServerExt($name) { return false; }
    public function mail($from) { $this->events['from'] = $from; return true; }
    public function recipient($address, $dsn = '') { $this->events['recipients'][] = $address; return true; }
    public function data($msg_data) { $this->events['data'] = $msg_data; return $this->dataAccepted; }
    public function reset() { return true; }
    public function quit($close_on_error = true) { $this->open = false; return true; }
    public function close() { $this->open = false; }
}

function register_smtp_mailer_tests(callable $test, callable $assert): void
{
    $test('SMTP authenticates, encrypts, preserves recipient and encodes Czech content', function () use ($assert): void {
        $original = $_ENV;
        try {
            $_ENV = array_merge($_ENV, ['SMTP_HOST'=>'smtp.example.test', 'SMTP_PORT'=>'465', 'SMTP_ENCRYPTION'=>'ssl', 'SMTP_USERNAME'=>'team@example.test', 'SMTP_PASSWORD'=>'fixture-password', 'MAIL_FROM'=>'team@example.test']);
            $smtp = new TestSmtpChannel(); $phpMailer = new PHPMailer(true); $phpMailer->setSMTPInstance($smtp);
            $service = new SmtpMailer($phpMailer);
            $assert($service->send('configured@example.test', 'Česká poptávka', "Zpráva česky\nDruhý řádek"));
            $assert($smtp->events['connect'][0] === 'ssl://smtp.example.test');
            $assert($smtp->events['connect'][1] === 465);
            $assert($smtp->events['connect'][2]['ssl'] === ['verify_peer'=>true,'verify_peer_name'=>true,'allow_self_signed'=>false]);
            $assert($smtp->events['auth'] === ['team@example.test','fixture-password']);
            $assert($smtp->events['from'] === 'team@example.test');
            $assert($smtp->events['recipients'] === ['configured@example.test']);
            $assert(str_contains(strtolower($smtp->events['data']), 'charset=utf-8') && str_contains($smtp->events['data'], 'quoted-printable'));
            $assert(str_contains(quoted_printable_decode($smtp->events['data']), 'Zpráva česky'));
            $assert(!str_contains($smtp->events['data'], 'fixture-password'));
            $assert($phpMailer->Mailer === 'smtp' && $phpMailer->SMTPDebug === 0 && !$smtp->open);
            $_ENV['SMTP_ENCRYPTION']='tls'; $_ENV['SMTP_PORT']='587';
            $assert($service->send('another@example.test', 'Reset', 'token fixture'));
            $assert($smtp->events['connect'][0] === 'smtp.example.test' && !empty($smtp->events['starttls']));
            $assert($phpMailer->getToAddresses() === [['another@example.test','']], 'Recipient must not leak between messages');
            $smtp->events['recipients'] = [];
            $assert($service->send('primary@example.test', 'Copy', 'Body', "copy@example.test, second@example.test\nPRIMARY@example.test copy@example.test"));
            $assert($phpMailer->getCcAddresses() === [['copy@example.test',''], ['second@example.test','']], 'Every unique configured CC must reach the SMTP envelope');
            $assert($smtp->events['recipients'] === ['primary@example.test', 'copy@example.test', 'second@example.test'], 'SMTP RCPT must include the primary and every unique copy recipient');
            $assert(str_contains($smtp->events['data'], 'Cc: copy@example.test, second@example.test'), 'All CC recipients must appear in message headers');
            $assert($service->send('primary@example.test', 'No copy', 'Body'));
            $assert($phpMailer->getCcAddresses() === [], 'Empty CC must clear previous recipients');
            $assert($service->send('primary@example.test', 'Duplicate', 'Body', 'PRIMARY@example.test primary@example.test'));
            $assert($phpMailer->getCcAddresses() === [], 'Same address must not receive a duplicate copy');
            foreach (['not-an-email', "copy@example.test\r\nBcc: injected@example.test"] as $badCopy) {
                try { $service->send('primary@example.test', 'Bad copy', 'Body', $badCopy); throw new \LogicException('Expected CC rejection'); }
                catch (SmtpTransportException $e) { $assert($e->errorCode === 'smtp_invalid_message'); }
            }
        } finally { $_ENV = $original; }
    });
    $test('SMTP rejects missing credentials, insecure config and sender mismatch before connecting', function () use ($assert): void {
        $original = $_ENV;
        try {
            $valid = ['SMTP_HOST'=>'smtp.example.test', 'SMTP_PORT'=>'465', 'SMTP_ENCRYPTION'=>'ssl', 'SMTP_USERNAME'=>'team@example.test', 'SMTP_PASSWORD'=>'fixture-password', 'MAIL_FROM'=>'team@example.test'];
            foreach (['SMTP_HOST'=>'ssl://bad.test', 'SMTP_PORT'=>'0', 'SMTP_ENCRYPTION'=>'none', 'SMTP_USERNAME'=>'bad', 'SMTP_PASSWORD'=>'', 'MAIL_FROM'=>'other@example.test'] as $key=>$bad) {
                $_ENV = array_merge($original, $valid, [$key=>$bad]);
                $smtp = new TestSmtpChannel();$mail = new PHPMailer(true);$mail->setSMTPInstance($smtp);
                try { (new SmtpMailer($mail))->send('configured@example.test','Test','Body'); throw new \LogicException('Expected configuration failure'); }
                catch (SmtpTransportException $e) { $assert($e->errorCode === 'smtp_configuration'); }
                $assert($smtp->events === [], 'Invalid config must not contact SMTP');
            }
        } finally { $_ENV = $original; }
    });
    $test('SMTP auth or DATA rejection is explicit and never falls back to mail', function () use ($assert): void {
        $original = $_ENV;
        try {
            $_ENV = array_merge($_ENV, ['SMTP_HOST'=>'smtp.example.test', 'SMTP_PORT'=>'465', 'SMTP_ENCRYPTION'=>'ssl', 'SMTP_USERNAME'=>'team@example.test', 'SMTP_PASSWORD'=>'fixture-password', 'MAIL_FROM'=>'team@example.test']);
            foreach (['authAccepted','dataAccepted'] as $phase) {
                $smtp=new TestSmtpChannel();$smtp->$phase=false;$mail=new PHPMailer(true);$mail->setSMTPInstance($smtp);
                try { (new SmtpMailer($mail))->send('configured@example.test','Reset','secret test body'); throw new \LogicException('Expected SMTP rejection'); }
                catch (SmtpTransportException $e) { $assert($e->errorCode==='smtp_transport' && $e->getPrevious()===null && !str_contains($e->getMessage(),'secret')); }
                $assert(!$smtp->open && $mail->Mailer==='smtp');
            }
        } finally { $_ENV=$original; }
    });
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    $assert = static function(bool $ok, string $message = 'Assertion failed'): void { if (!$ok) { throw new RuntimeException($message); } };
    register_smtp_mailer_tests(static function(string $name, callable $fn): void { $fn(); echo "PASS: {$name}\n"; }, $assert);
}
