<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/vendor/autoload.php';
use App\Core\Database;
use App\Core\Env;
use App\Repositories\PortfolioRepository;
use App\Services\ApprovedVideoPortfolioService;
Env::load(dirname(__DIR__) . '/.env');
$db = new PDO(sprintf('mysql:host=%s;port=%s;charset=utf8mb4', Env::get('DB_HOST'), Env::get('DB_PORT', '3306')), Env::get('DB_USER'), Env::get('DB_PASS'), [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$name = 'rbvp_video_test_' . bin2hex(random_bytes(6));
$slug = 'retired-video-test-' . bin2hex(random_bytes(6));
$dir = dirname(__DIR__) . '/storage/published/nase-prace/' . $slug;
$export = $dir . '/index.html';
$db->exec('CREATE DATABASE `' . $name . '`');
$checks=0;
function assertVideo(bool $ok,string $message):void {global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
try {
    $db->exec('USE `' . $name . '`');
    foreach (['001_schema.sql','009_add_default_hero_media_to_sites.sql','065_add_portfolio_type_and_shared_sections.sql','071_create_portfolio_videos.sql'] as $file) $db->exec(file_get_contents(dirname(__DIR__) . '/database/migrations/' . $file));
    (new ReflectionProperty(Database::class,'instance'))->setValue(null,$db);
    $db->exec("INSERT INTO sites (name,domain) VALUES ('Primary','https://fixture.test'),('Other','https://other.test')");
    $db->exec("UPDATE sites SET default_hero_video_url='https://vimeo.com/123',default_hero_poster_url='/media/hero.jpg' WHERE id=1");
    $repo = new PortfolioRepository();
    $photo = $repo->create(['site_id'=>1,'slug'=>'preserved-photo','title'=>'Photo','content_type'=>'photo','video_url'=>'https://vimeo.com/111','sort_order'=>8,'status'=>'published']);
    $other = $repo->create(['site_id'=>2,'slug'=>'other-site-video','title'=>'Other','content_type'=>'video','video_url'=>'https://youtu.be/aaaaaaaaaaa','sort_order'=>1,'status'=>'published']);
    $old = $repo->create(['site_id'=>1,'slug'=>$slug,'title'=>'Retired','content_type'=>'video','video_url'=>'https://youtu.be/bbbbbbbbbbb','sort_order'=>1,'status'=>'draft']);
    $repo->setVideos($old,['https://youtu.be/bbbbbbbbbbb','https://youtu.be/ccccccccccc']);
    $first = ApprovedVideoPortfolioService::MANIFEST[0];
    $existing = $repo->create(['site_id'=>1,'slug'=>$first['slug'],'title'=>'Outdated','content_type'=>'video','video_url'=>'https://youtu.be/ddddddddddd','sort_order'=>99,'status'=>'published']);
    $repo->setVideos($existing,['https://youtu.be/ddddddddddd','https://youtu.be/eeeeeeeeeee']);
    $db->exec("INSERT INTO media (site_id,original_filename,storage_path,mime_type) VALUES (1,'keep.jpg','storage/media/keep.jpg','image/jpeg')");
    $repo->setMedia($photo,[1]);$repo->setMedia($old,[1]);$repo->setMedia($existing,[1]);
    $photoBefore=$repo->findById($photo);$otherBefore=$repo->findById($other);
    $mediaBefore=$db->query('SELECT * FROM media')->fetchAll();$sitesBefore=$db->query('SELECT * FROM sites')->fetchAll();
    assertVideo(!file_exists($dir),'Own random export path is unused');mkdir($dir,0755,true);file_put_contents($export,'Old video');
    assertVideo((new ApprovedVideoPortfolioService())->replace(1)===12,'Exactly twelve approved items applied');
    $entries=$db->query("SELECT * FROM portfolio_entries WHERE site_id=1 AND content_type='video' ORDER BY sort_order,id")->fetchAll();
    $ids=['L-zaa5hj-aU','cXU7Ty-kjyA','hYj2w3P5rK8','5ZlVVcIu10U','O5i9TzCms5c','b9WhNN8VYhc','C1wL3QFZhn0','DBWKJ46eM4c','su0hKnBnCKw','1z-qckQn5UU','o-1xGllIShY','GBKJ_VB_Nmw'];
    assertVideo(array_column($entries,'video_url')===array_map(static fn($id)=>'https://www.youtube.com/watch?v='.$id,$ids),'URLs match user order 1-12 exactly');
    assertVideo(array_column($entries,'sort_order')===range(9,20),'Video order follows preserved photo order without reordering photos');
    assertVideo($repo->findById($photo)===$photoBefore && $repo->getMedia($photo)[0]['id']===1,'Photo entry and its attached media preserved');
    assertVideo($repo->findById($other)===$otherBefore,'Another site preserved');
    assertVideo($db->query('SELECT * FROM media')->fetchAll()===$mediaBefore,'Physical media records preserved');
    assertVideo($db->query('SELECT * FROM sites')->fetchAll()===$sitesBefore,'Global site/hero settings preserved');
    assertVideo(!$repo->findById($old) && !file_exists($export),'Retired draft/video export removed');
    assertVideo((int)$entries[0]['id']===$existing,'Matching approved slug retains its entry identity');
    foreach($entries as $i=>$entry){
        $videos=$repo->getVideos((int)$entry['id']);
        assertVideo(count($videos)===1 && $videos[0]['url']===$entry['video_url'],'No old secondary videos remain');
        assertVideo($repo->getMedia((int)$entry['id'])===[],'YouTube thumbnail replaces old video photo association');
        assertVideo($entry['status']==='published' && str_contains($videos[0]['thumbnail'],$ids[$i]),'Published entry has correct YouTube thumbnail');
    }
    $beforeRepeat=$db->query('SELECT * FROM portfolio_entries ORDER BY id')->fetchAll();
    $videosBefore=$db->query('SELECT * FROM portfolio_videos ORDER BY id')->fetchAll();
    (new ApprovedVideoPortfolioService())->replace(1);
    assertVideo($db->query('SELECT * FROM portfolio_entries ORDER BY id')->fetchAll()===$beforeRepeat,'Repeat preserves complete entries including IDs/timestamps');
    assertVideo($db->query('SELECT * FROM portfolio_videos ORDER BY id')->fetchAll()===$videosBefore,'Repeat does not duplicate/rewrite video rows');
    echo "PASS: {$checks} approved-video replacement assertions.\n";
} finally {
    if(is_file($export))unlink($export);
    if(is_dir($dir))rmdir($dir);
    $db->exec('DROP DATABASE `' . $name . '`');
}
