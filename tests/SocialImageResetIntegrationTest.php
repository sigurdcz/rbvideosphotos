<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/vendor/autoload.php';
use App\Core\Env;
use App\Services\PortfolioPublishService;
use App\Repositories\PortfolioRepository;
Env::load(dirname(__DIR__).'/.env');
$db=new PDO(sprintf('mysql:host=%s;port=%s;charset=utf8mb4',Env::get('DB_HOST'),Env::get('DB_PORT','3306')),Env::get('DB_USER'),Env::get('DB_PASS'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$name='rbvp_social_test_'.bin2hex(random_bytes(6));$db->exec('CREATE DATABASE `'.$name.'`');
$slug='social-image-test-'.bin2hex(random_bytes(6));
$export=dirname(__DIR__).'/storage/published/nase-prace/'.$slug.'/index.html';
$checks=0;ob_start();
function check(bool $ok,string $message):void{global $checks;if(!$ok){throw new RuntimeException($message);}$checks++;}
final class SocialImageTestPortfolio extends PortfolioRepository {
 public function getMedia(int $portfolioId):array{return [['storage_path'=>'storage/media/route-hero.webp']];}
 public function getVideos(int $portfolioId):array{return [];}
}
try{
 $db->exec('USE `'.$name.'`');
 foreach(['001_schema.sql','007_add_site_brand_assets.sql','024_add_default_og_image_to_sites.sql','026_add_platform_social_images.sql','079_structured_schema_org_fields.sql'] as $file){$db->exec(file_get_contents(dirname(__DIR__).'/database/migrations/'.$file));}
 $db->exec("INSERT INTO sites (name,domain) VALUES ('One','https://example.test'),('Two','https://two.example.test')");
 $stmt=$db->prepare("INSERT INTO media (site_id,original_filename,storage_path,mime_type) VALUES (1,?,?,'image/webp')");
 foreach(['global-og','global-twitter','global-instagram','old-page-photo','schema-photo'] as $file){$stmt->execute([$file.'.webp','storage/media/'.$file.'.webp']);}
 $db->exec('UPDATE sites SET default_og_image_id=1,default_twitter_image_id=2,default_instagram_image_id=3 WHERE id=1');
 $db->exec("INSERT INTO pages (site_id,title,slug,url_path,status) VALUES (1,'Kontakt','kontakt','/kontakt','published'),(1,'Draft','draft','/draft','draft'),(2,'Other','other','/other','published')");
 $db->exec("INSERT INTO seo_settings (page_id,meta_title,og_title,og_image_id,twitter_image_id,schema_image_id) VALUES (1,'Keep title','Keep OG title',4,4,5),(2,'Draft title','Draft OG',NULL,4,5),(3,'Other title','Other OG',4,NULL,NULL)");
 $sitesBefore=$db->query('SELECT * FROM sites ORDER BY id')->fetchAll();
 $seoBefore=$db->query('SELECT * FROM seo_settings ORDER BY id')->fetchAll();
 $mediaBefore=$db->query('SELECT * FROM media ORDER BY id')->fetchAll();
 (new ReflectionProperty(App\Core\Database::class,'instance'))->setValue(null,$db);
 define('SEO_CMS_GOLDEN_BOOTSTRAP',true);$_SERVER['REQUEST_METHOD']='GET';$_SERVER['REQUEST_URI']='/__social_test__';
 require dirname(__DIR__).'/public/index.php';
 restore_exception_handler();restore_error_handler();
 $site=$db->query('SELECT * FROM sites WHERE id=1')->fetch();
 $source='<html><head><title>Fixture</title></head><body>Fixture</body></html>';
 $meta=['path'=>'/kontakt','og_image'=>'/media/route-hero.webp','twitter_image'=>'/media/route-twitter.webp'];
 $before=injectPageHeadMetadata($source,$site,$meta);
 check(str_contains($before,'property="og:image" content="https://example.test/media/old-page-photo.webp"'),'Existing page override is explicit before migration');
 $migration=file_get_contents(dirname(__DIR__).'/database/migrations/105_clear_page_social_image_overrides.sql');
 $db->exec($migration);
 check((int)$db->query('SELECT COUNT(*) FROM seo_settings WHERE og_image_id IS NOT NULL OR twitter_image_id IS NOT NULL')->fetchColumn()===0,'All published/draft/multisite overrides cleared');
 $after=$db->query('SELECT * FROM seo_settings ORDER BY id')->fetchAll();
 foreach($seoBefore as $i=>$row){$row['og_image_id']=null;$row['twitter_image_id']=null;check($after[$i]===$row,'All other SEO fields preserved');}
 check($db->query('SELECT * FROM sites ORDER BY id')->fetchAll()===$sitesBefore,'Global platform images preserved');
 check($db->query('SELECT * FROM media ORDER BY id')->fetchAll()===$mediaBefore,'Media records preserved');
 $db->exec($migration);check($db->query('SELECT * FROM seo_settings ORDER BY id')->fetchAll()===$after,'Repeat migration is idempotent');
 $html=injectPageHeadMetadata($source,$site,$meta);
 check(str_contains($html,'property="og:image" content="https://example.test/media/global-og.webp"'),'Contact inherits global Open Graph');
 check(str_contains($html,'name="twitter:image" content="https://example.test/media/global-twitter.webp"'),'Contact inherits platform-specific global Twitter');
 $detail=injectPageHeadMetadata($source,$site,['path'=>'/portfolio-detail','og_image'=>'/media/route-hero.webp']);
 check(str_contains($detail,'property="og:image" content="https://example.test/media/global-og.webp"'),'Global image takes priority over detail hero');
 $withoutTwitter=$site;$withoutTwitter['default_twitter_image_id']=null;
 $html=injectPageHeadMetadata($source,$withoutTwitter,$meta);
 check(str_contains($html,'name="twitter:image" content="https://example.test/media/global-og.webp"'),'Twitter inherits global OG when no platform-specific selection');
 check(!file_exists($export),'Isolated export path is new');
 (new PortfolioPublishService())->publishEntry(['id'=>1,'title'=>'Fixture','slug'=>$slug,'description'=>'Fixture'],new SocialImageTestPortfolio(),$site);
 $html=file_get_contents($export);
 check(str_contains($html,'property="og:image" content="https://example.test/media/global-og.webp"'),'Portfolio static export uses global OG');
 check(str_contains($html,'name="twitter:image" content="https://example.test/media/global-twitter.webp"'),'Portfolio static export uses global Twitter');
 $db->exec('UPDATE seo_settings SET og_image_id=4,twitter_image_id=4 WHERE page_id=1');
 $seeder=(new ReflectionClass(App\Services\DemoContentSeeder::class))->newInstanceWithoutConstructor();
 (new ReflectionProperty(App\Services\DemoContentSeeder::class,'db'))->setValue($seeder,$db);
 (new ReflectionProperty(App\Services\DemoContentSeeder::class,'pages'))->setValue($seeder,new App\Repositories\PageRepository());
 (new ReflectionMethod(App\Services\DemoContentSeeder::class,'seedPageSeo'))->invoke($seeder,1,$site);
 check((int)$db->query('SELECT COUNT(*) FROM seo_settings WHERE og_image_id IS NOT NULL OR twitter_image_id IS NOT NULL')->fetchColumn()===0,'Current demo SEO seeding does not restore legacy sharing overrides');
 ob_end_clean();echo "PASS: {$checks} social-image migration/inheritance/export assertions.\n";
}finally{
 if(is_file($export)){unlink($export);rmdir(dirname($export));}
 $db->exec('DROP DATABASE `'.$name.'`');
}
