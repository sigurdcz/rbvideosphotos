<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/vendor/autoload.php';
use App\Core\Env;
use App\Services\AccountPasswordService;
use App\Repositories\MailArchiveRepository;
Env::load(dirname(__DIR__) . '/.env');
$db = new PDO(sprintf('mysql:host=%s;port=%s;charset=utf8mb4', Env::get('DB_HOST'), Env::get('DB_PORT','3306')), Env::get('DB_USER'), Env::get('DB_PASS'), [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$name = 'rbvp_account_test_' . bin2hex(random_bytes(6));
$db->exec('CREATE DATABASE `' . $name . '`');
$checks=0;
function check(bool $ok, string $message): void { global $checks; if (!$ok) { throw new RuntimeException($message); } $checks++; }
function reject(Closure $fn): void { try { $fn(); } catch (InvalidArgumentException $e) { return; } throw new RuntimeException('Expected validation failure'); }
try {
 $db->exec('USE `' . $name . '`');
 $db->exec("CREATE TABLE users (id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT, email VARCHAR(255), password_hash VARCHAR(255)) ENGINE=InnoDB");
 $db->exec(file_get_contents(dirname(__DIR__).'/database/migrations/103_add_password_resets.sql'));
 $db->exec(file_get_contents(dirname(__DIR__).'/database/migrations/104_add_mail_archive.sql'));
 $old=password_hash('old-password-123', PASSWORD_BCRYPT);
 $db->prepare('INSERT INTO users (email,password_hash) VALUES (?,?),(?,?)')->execute(['one@example.test',$old,'two@example.test',$old]);
 $db->exec("INSERT INTO password_resets VALUES (1, REPEAT('a',64),'2030-01-01'),(2,REPEAT('b',64),'2030-01-01')");
 $service=new AccountPasswordService($db);
 reject(fn()=>$service->change(1,'wrong','new-password-123'));
 reject(fn()=>$service->change(1,'old-password-123','short'));
 reject(fn()=>$service->change(1,'old-password-123',str_repeat('a',73)));
 reject(fn()=>$service->change(1,'old-password-123',"new-password\0bad"));
 reject(fn()=>$service->change(1,'old-password-123','old-password-123'));
 reject(fn()=>$service->change(999,'old-password-123','new-password-123'));
 check($db->query('SELECT password_hash FROM users WHERE id=1')->fetchColumn()===$old,'Failed attempts preserve hash');
 check((int)$db->query('SELECT COUNT(*) FROM password_resets')->fetchColumn()===2,'Failed attempts preserve tokens');
 $service->change(1,'old-password-123','new-password-123');
 $hash=$db->query('SELECT password_hash FROM users WHERE id=1')->fetchColumn();
 check(password_verify('new-password-123',$hash) && !password_verify('old-password-123',$hash),'New password verifies, old rejected');
 check($db->query('SELECT password_hash FROM users WHERE id=2')->fetchColumn()===$old,'Other user untouched');
 check($db->query('SELECT user_id FROM password_resets')->fetchAll(PDO::FETCH_COLUMN)===[2],'Only own reset token revoked');
 check(hash('sha256',$old)!==hash('sha256',$hash),'Existing session fingerprint becomes invalid');
 $db->exec('DROP TABLE password_resets');
 try { $service->change(1,'new-password-123','next-password-123'); throw new RuntimeException('Expected transaction failure'); }
 catch (PDOException $e) { check($db->query('SELECT password_hash FROM users WHERE id=1')->fetchColumn()===$hash,'Token revocation failure rolls password update back'); }
 $stmt=$db->prepare('INSERT INTO mail_archive (kind,recipient,subject,body,status,created_at,attempted_at) VALUES (?,?,?,?,?,?,?)');
 for($i=0;$i<55;$i++) { $stmt->execute(['contact','configured@example.test','Contact '.$i,'Body','accepted','2026-10-16','2026-10-16']); }
 $stmt->execute(['password_reset','one@example.test','Reset',null,'failed','2026-10-16','2026-10-16']);
 $stmt->execute(['contact','configured@example.test','Pending','Saved','stored','2026-10-16',null]);
 $repo=new MailArchiveRepository($db);
 $data=$repo->overview();check($data['total']===57 && count($data['rows'])===50,'Overview pagination count');
 check($data['counts']===['stored'=>1,'accepted'=>55,'failed'=>1],'Overview honest status totals');
 $data=$repo->overview('accepted','contact',2);check($data['total']===55 && count($data['rows'])===5,'Combined filters preserve pagination');
 $data=$repo->overview('failed','password_reset');check($data['total']===1 && $data['rows'][0]['body']===null,'Reset metadata visible without secret');
 reject(fn()=>$repo->overview("accepted' OR 1=1",'',1));
 reject(fn()=>$repo->overview('','unknown',1));
 reject(fn()=>$repo->overview('','',0));
 check($repo->overview('stored')['rows'][0]['attempted_at']===null,'Pending attempt stays visibly unconfirmed');
 echo "PASS: {$checks} account/overview assertions plus invalid password/filter cases.\n";
} finally { $db->exec('DROP DATABASE `' . $name . '`'); }
