<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/vendor/autoload.php';
use App\Core\Env;
use App\Repositories\ContactRepository;
Env::load(dirname(__DIR__).'/.env');
$db=new PDO(sprintf('mysql:host=%s;port=%s;charset=utf8mb4',Env::get('DB_HOST'),Env::get('DB_PORT','3306')),Env::get('DB_USER'),Env::get('DB_PASS'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$name='rbvp_recovery_policy_'.bin2hex(random_bytes(6));$db->exec('CREATE DATABASE `'.$name.'`');$checks=0;
function check(bool $ok,string $message):void{global $checks;if(!$ok){throw new RuntimeException($message);}$checks++;}
try{
 $db->exec('USE `'.$name.'`');$db->exec(file_get_contents(dirname(__DIR__).'/database/migrations/001_schema.sql'));
 $db->exec(file_get_contents(dirname(__DIR__).'/database/migrations/028_add_reviews_url_to_contact_info.sql'));
 $db->exec("INSERT INTO sites (name,domain) VALUES ('One','one.test'),('Two','two.test')");
 (new ReflectionProperty(App\Core\Database::class,'instance'))->setValue(null,$db);
 $repo=new ContactRepository();$repo->upsert(1,['form_email'=>'central@example.test','email'=>'public@example.test'],true);
 check($repo->findBySite(1)['form_email']==='central@example.test','Admin can configure central address');
 try{$repo->upsert(1,['form_email'=>'attacker@example.test']);throw new LogicException('Expected denial');}catch(RuntimeException $e){check($repo->findBySite(1)['form_email']==='central@example.test','Untrusted change rejected');}
 $repo->upsert(1,['email'=>'new-public@example.test']);
 check($repo->findBySite(1)['form_email']==='central@example.test','Unrelated edit preserves protected recipient');
 $repo->upsert(1,['form_email'=>'central@example.test','phone_1'=>'123']);
 check($repo->findBySite(1)['form_email']==='central@example.test','Unchanged readonly field is allowed');
 $repo->upsert(1,['form_email'=>'new-central@example.test'],true);
 check($repo->findBySite(1)['form_email']==='new-central@example.test','Authorized change works');
 $repo->upsert(1,['phone_1'=>'456'],true);
 check($repo->findBySite(1)['form_email']==='new-central@example.test','Omitted field preserved for admin too');
 $repo->upsert(2,['email'=>'public2@example.test']);
 check($repo->findBySite(2)['form_email']===null,'Untrusted insert does not invent a recovery address');
 echo "PASS: {$checks} recovery recipient permission assertions.\n";
}finally{$db->exec('DROP DATABASE `'.$name.'`');}
