<?php

$root = dirname(__DIR__);
$read = static fn(string $path): string => (string)file_get_contents($root . '/' . $path);
$failures = [];
$expect = static function(bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$expect(str_contains($read('admin/index.php'), "'/admin/media/picker'"), 'Chybi picker route.');
$expect(str_contains($read('admin/assets/admin.js'), "fetch('/admin/media/picker?'"), 'Picker nenacita knihovnu pres API.');
$expect(str_contains($read('admin/views/blog/form.php'), "mode:'multiple'"), 'Chybi multi-select rezim.');
$expect(!str_contains($read('app/Controllers/BlogController.php'), 'allMedia()'), 'Blog stale nacita celou knihovnu.');
$expect(!str_contains($read('app/Controllers/PortfolioController.php'), 'allMedia()'), 'Portfolio stale nacita celou knihovnu.');
$expect(!str_contains($read('app/Controllers/PageController.php'), 'allBySiteWithVariants'), 'Editor stranek stale nacita celou knihovnu.');
$expect(!str_contains($read('app/Controllers/SeoController.php'), 'allBySiteWithVariants'), 'SEO editor stale nacita celou knihovnu.');
$expect(!str_contains($read('app/Controllers/SettingsController.php'), 'allBySiteWithVariants'), 'Nastaveni stale nacita celou knihovnu.');
$pageForm = $read('admin/views/pages/form.php');
$mediaController = $read('app/Controllers/MediaController.php');
$expect(str_contains($pageForm, 'data-homepage-about-visual-upload') && str_contains($pageForm, "fetch('/admin/media/upload'"), 'Homepage editor nema primy upload fotografie sekce O nas.');
$expect(str_contains($pageForm, "formData.append('images_only', '1')") && str_contains($mediaController, "\$imagesOnly"), 'Homepage upload nevynucuje obrazkovy typ na klientovi i serveru.');
$expect(str_contains($mediaController, "'url' => \$this->publicMediaUrl") && str_contains($pageForm, 'input.value = result.url'), 'Nahrane homepage medium se po uploadu automaticky nevybere.');

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "Admin media picker contract OK\n";
