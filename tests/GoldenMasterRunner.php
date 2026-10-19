<?php

declare(strict_types=1);

/*
 * Golden-master test runner for Fáze 0 of the template refactor
 * (see TEMPLATE_REFACTOR_PLAN.md).
 *
 * Captures normalized HTML snapshots of every public route — rendered exactly the
 * way public/index.php renders them for an anonymous visitor — and of the static
 * publish output produced by App\Services\PublishService, so later refactor
 * phases (1-6) can diff current behaviour against a frozen baseline and catch
 * unintended regressions.
 *
 * Usage (wired up from tests/run.php):
 *   GoldenMasterRunner::update() -> (re)writes tests/goldens/{live,publish}/*.normalized.html
 *   GoldenMasterRunner::test()   -> compares a fresh render against the stored goldens
 *
 * No real HTTP server is involved: public/index.php is require()'d once (with a
 * SEO_CMS_GOLDEN_BOOTSTRAP guard that skips its normal routing dispatch — see the
 * bottom of that file) purely to obtain the tryServe*() function definitions and
 * the app bootstrap (Env/Config/Runtime/Auth). Each route is then rendered by
 * calling the relevant tryServe*() function directly inside an output buffer.
 */

use App\Core\Database;
use App\Repositories\PageRepository;
use App\Repositories\SiteRepository;
use App\Services\PortfolioPublishService;
use App\Services\PublishService;

/**
 * Strips non-deterministic fragments from rendered HTML so that two renders of
 * the same underlying content produce byte-identical output:
 *  - the "<!-- cms: vX.Y.Z published: ... -->" stamp PublishService writes,
 *  - the per-session CSRF token embedded in the /kontakt form (and, in edit
 *    mode, the admin chrome's inline-csrf meta tag),
 *  - content-hashed generated asset filenames (sha1-of-content cache busting),
 *  - the yearly "&copy; <year>" footer date,
 *  - and, only in edit mode, the data-inline-* attributes the inline editor
 *    injects (present only for an authenticated request; irrelevant noise for
 *    a structural diff).
 *
 * Structure, classes, ids, aria attributes and textual content are left untouched.
 */
function normalizeHtmlForGolden(string $html, bool $editMode = false): string
{
    $html = str_replace(["\r\n", "\r"], "\n", $html);

    // Database revision diagnostics vary with the local fixture database; they
    // are not page content and must not invalidate every route snapshot.
    $html = preg_replace('~<!-- build [^\r\n]*?\| migrations: [^\r\n]*?-->~', '<!-- build BUILD | migrations: MIGRATIONS -->', $html) ?? $html;

    // Publish stamp, e.g. "<!-- cms: v_1.3.0 published: 2026-07-17 12:34:56 -->".
    $html = preg_replace(
        '/<!--\s*cms:\s*\S+\s+published:\s*\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}\s*-->\n?/',
        '',
        $html
    ) ?? $html;

    // "&copy; 2026" -> "&copy; YYYY" (date('Y') footer copyright).
    $html = preg_replace('/(&copy;\s*)\d{4}/', '${1}YYYY', $html) ?? $html;

    // Per-session CSRF token values (App\Core\Auth::csrfToken()).
    $html = preg_replace('/(name="_csrf"\s+value=")[a-f0-9]{16,}(")/i', '${1}CSRF_TOKEN${2}', $html) ?? $html;
    $html = preg_replace('/(name="inline-csrf"\s+content=")[a-f0-9]{16,}(")/i', '${1}CSRF_TOKEN${2}', $html) ?? $html;

    // The inlined @font-face block (App\Services\Render\MarkupNormalizer::inlineFontFaces).
    // Kept as a placeholder so the goldens still prove it is present and injected once,
    // without 25 files churning every time a font file is renamed.
    $html = preg_replace(
        '~(<style data-cms-fonts-css>).*?(</style>)~s',
        '${1}FONT_FACES${2}',
        $html
    ) ?? $html;

    // Content-hashed generated asset filenames (App\Support\FrontendAssetManager).
    $html = preg_replace(
        '/((?:page-css|page-js|body-vars|video-overlay|inline-style-blocks|inline-body-scripts|cookie-consent-runtime|cookie-consent|frontend-runtime|tracking|visit-analytics)-)[0-9a-f]{16}(\.(?:css|js))/',
        '${1}HASH${2}',
        $html
    ) ?? $html;

    // Defensive: any raw session id that might leak into a link/debug attribute.
    $html = preg_replace('/\b(PHPSESSID|seo_cms_session)=[A-Za-z0-9,\-]+/', '$1=SESSION_ID', $html) ?? $html;

    if ($editMode) {
        $html = preg_replace('/\s+data-inline-key="[^"]*"\s+data-inline-type="[^"]*"/', '', $html) ?? $html;
    }

    return rtrim($html, "\n") . "\n";
}

final class GoldenMasterRunner
{
    private const LIVE_DIR = __DIR__ . '/goldens/live';
    private const PUBLISH_DIR = __DIR__ . '/goldens/publish';

    private static bool $bootstrapped = false;
    private static ?int $cachedUserId = null;

    /** @return array{lines: string[], failures: int} */
    public static function update(): array
    {
        self::bootstrap();
        $lines = [];
        $failures = 0;

        if (!is_dir(self::LIVE_DIR) && !@mkdir(self::LIVE_DIR, 0775, true) && !is_dir(self::LIVE_DIR)) {
            throw new RuntimeException('Could not create ' . self::LIVE_DIR);
        }
        if (!is_dir(self::PUBLISH_DIR) && !@mkdir(self::PUBLISH_DIR, 0775, true) && !is_dir(self::PUBLISH_DIR)) {
            throw new RuntimeException('Could not create ' . self::PUBLISH_DIR);
        }

        foreach (self::liveRoutes() as $key => $uri) {
            try {
                $html = normalizeHtmlForGolden(self::renderLiveRoute($uri));
                file_put_contents(self::LIVE_DIR . '/' . $key . '.normalized.html', $html);
                $lines[] = "WROTE {$key} live ({$uri})";
            } catch (Throwable $e) {
                $failures++;
                $lines[] = "ERROR {$key} live ({$uri}): " . $e->getMessage();
            }
        }

        foreach (self::publishTargets() as $key => $target) {
            try {
                $html = normalizeHtmlForGolden(self::renderPublishTarget($target));
                file_put_contents(self::PUBLISH_DIR . '/' . $key . '.normalized.html', $html);
                $lines[] = "WROTE {$key} publish";
            } catch (Throwable $e) {
                $failures++;
                $lines[] = "ERROR {$key} publish: " . $e->getMessage();
            }
        }

        return ['lines' => $lines, 'failures' => $failures];
    }

    /** @return array{lines: string[], failures: int} */
    public static function test(): array
    {
        self::bootstrap();
        $lines = [];
        $failures = 0;

        if (!is_dir(self::LIVE_DIR) && !is_dir(self::PUBLISH_DIR)) {
            return ['lines' => ['No goldens found — run: php tests/run.php --update-goldens'], 'failures' => 0];
        }

        foreach (self::liveRoutes() as $key => $uri) {
            $goldenFile = self::LIVE_DIR . '/' . $key . '.normalized.html';
            [$line, $failed] = self::compareOne(
                "{$key} live",
                $goldenFile,
                fn (): string => normalizeHtmlForGolden(self::renderLiveRoute($uri))
            );
            $lines[] = $line;
            $failures += $failed ? 1 : 0;
        }

        foreach (self::publishTargets() as $key => $target) {
            $goldenFile = self::PUBLISH_DIR . '/' . $key . '.normalized.html';
            [$line, $failed] = self::compareOne(
                "{$key} publish",
                $goldenFile,
                fn (): string => normalizeHtmlForGolden(self::renderPublishTarget($target))
            );
            $lines[] = $line;
            $failures += $failed ? 1 : 0;
        }

        [$line, $failed] = self::portfolioListingRegenerationCheck();
        $lines[] = $line;
        $failures += $failed ? 1 : 0;

        return ['lines' => $lines, 'failures' => $failures];
    }

    /**
     * Phase-6 BLOCKER regression guard. The admin portfolio CRUD path
     * (ApiController/PortfolioController → PortfolioPublishService::regenerateListing)
     * must regenerate storage/published/nase-prace/index.html with the SAME themed
     * output PublishService::publishPage produces (the nase-prace publish golden),
     * NOT the old bare pl-grid/pl-tile markup of the deleted dual writer
     * (regenerateListingPage). The rest of the golden runner never exercises
     * PortfolioPublishService, so this scenario would otherwise be untested — see
     * UKOL.md "Fáze 6".
     *
     * @return array{0: string, 1: bool}
     */
    private static function portfolioListingRegenerationCheck(): array
    {
        return self::compareOne(
            'nase-prace portfolio-crud regen',
            self::PUBLISH_DIR . '/nase-prace.normalized.html',
            static function (): string {
                $site = (new SiteRepository())->findFirst();
                if (!$site) {
                    throw new RuntimeException('No site configured.');
                }

                (new PortfolioPublishService())->regenerateListing($site, self::firstAdminUserId());

                $file = dirname(__DIR__) . '/storage/published/nase-prace/index.html';
                $html = file_get_contents($file);
                if ($html === false) {
                    throw new RuntimeException('Could not read regenerated listing: ' . $file);
                }

                return normalizeHtmlForGolden($html);
            }
        );
    }

    /**
     * @param callable(): string $render
     * @return array{0: string, 1: bool}
     */
    private static function compareOne(string $label, string $goldenFile, callable $render): array
    {
        try {
            $current = $render();
        } catch (Throwable $e) {
            return ["{$label}: ERROR (" . $e->getMessage() . ')', true];
        }

        if (!is_file($goldenFile)) {
            return ["{$label}: MISSING (no golden — run --update-goldens)", true];
        }

        $golden = normalizeHtmlForGolden((string) file_get_contents($goldenFile));
        if ($golden === $current) {
            return ["{$label}: OK", false];
        }

        [$added, $removed] = self::lineDiffCounts($golden, $current);
        return ["{$label}: CHANGED (+{$added} -{$removed})", true];
    }

    /**
     * Line-level LCS diff, used only to report +added/-removed counts (not a
     * full unified diff).
     *
     * @return array{0: int, 1: int} [added, removed]
     */
    private static function lineDiffCounts(string $old, string $new): array
    {
        $a = explode("\n", $old);
        $b = explode("\n", $new);
        $m = count($a);
        $n = count($b);

        $dp = array_fill(0, $m + 1, null);
        for ($i = 0; $i <= $m; $i++) {
            $dp[$i] = array_fill(0, $n + 1, 0);
        }
        for ($i = $m - 1; $i >= 0; $i--) {
            for ($j = $n - 1; $j >= 0; $j--) {
                $dp[$i][$j] = $a[$i] === $b[$j]
                    ? $dp[$i + 1][$j + 1] + 1
                    : max($dp[$i + 1][$j], $dp[$i][$j + 1]);
            }
        }

        $removed = 0;
        $added = 0;
        $i = 0;
        $j = 0;
        while ($i < $m && $j < $n) {
            if ($a[$i] === $b[$j]) {
                $i++;
                $j++;
            } elseif ($dp[$i + 1][$j] >= $dp[$i][$j + 1]) {
                $removed++;
                $i++;
            } else {
                $added++;
                $j++;
            }
        }
        $removed += $m - $i;
        $added += $n - $j;

        return [$added, $removed];
    }

    /**
     * require()s public/index.php exactly once with SEO_CMS_GOLDEN_BOOTSTRAP
     * defined, so its routing dispatch is skipped and only the tryServe*()
     * function definitions plus the app bootstrap (Env/Runtime/Auth) run.
     */
    private static function bootstrap(): void
    {
        if (self::$bootstrapped) {
            return;
        }

        if (!defined('SEO_CMS_GOLDEN_BOOTSTRAP')) {
            define('SEO_CMS_GOLDEN_BOOTSTRAP', true);
        }

        $_SERVER['REQUEST_URI'] = '/__golden_master_bootstrap__';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $_GET = [];
        $_POST = [];

        ob_start();
        require dirname(__DIR__) . '/public/index.php';
        ob_end_clean();

        self::$bootstrapped = true;
    }

    /** @return array<string, string> route key => URI, in a stable order */
    private static function liveRoutes(): array
    {
        $routes = [
            'home' => '/',
            'sluzby' => '/sluzby',
            'cenik' => '/cenik',
            'faq' => '/faq',
            'kontakt' => '/kontakt',
            'blog' => '/blog',
            'nase-prace' => '/nase-prace',
            'hodnoceni' => '/hodnoceni',
            // "podminky-uziti" is a generic DB page (no dedicated tryServe*()
            // handler) — it stands in for the task's "/o-nas" example, which
            // does not exist in this project's demo dataset.
            'generic-page' => '/podminky-uziti',
            '404' => '/__golden_master_404_probe__',
        ];

        $site = (new SiteRepository())->findFirst();
        $siteId = $site ? (int) $site['id'] : 0;

        if ($siteId > 0) {
            $post = self::firstRow(
                'SELECT slug FROM blog_posts WHERE site_id = ? AND status = ? ORDER BY id ASC LIMIT 1',
                [$siteId, 'published']
            );
            if ($post) {
                $routes['blog-detail'] = '/' . $post['slug'];
            }

            $entry = self::firstRow(
                'SELECT slug FROM portfolio_entries WHERE site_id = ? AND status = ? ORDER BY id ASC LIMIT 1',
                [$siteId, 'published']
            );
            if ($entry) {
                $routes['portfolio-detail'] = '/' . $entry['slug'];
            }
        }

        return $routes;
    }

    /**
     * All statically published targets: every published `pages` row (rendered
     * via PublishService::publishPage) plus the standalone 404 page. Blog posts
     * and portfolio entries are served dynamically only (see storage/published/
     * on disk — there is no per-post/per-entry static export), so they are not
     * publish targets.
     *
     * @return array<string, array{type: string, page?: array, site: array}>
     */
    private static function publishTargets(): array
    {
        $site = (new SiteRepository())->findFirst();
        if (!$site) {
            return [];
        }

        $targets = [];
        foreach ((new PageRepository())->findPublishedBySite((int) $site['id']) as $page) {
            $slug = trim((string) ($page['url_path'] ?? ''), '/');
            $key = $slug === '' ? 'home' : $slug;
            $targets[$key] = ['type' => 'page', 'page' => $page, 'site' => $site];
        }
        ksort($targets);

        $targets['404'] = ['type' => '404', 'site' => $site];

        return $targets;
    }

    /**
     * Renders one live route the same way public/index.php's dispatch does for
     * an anonymous (unauthenticated, non-preview) GET request. The asset/media
     * static-file and legacy-redirect branches of that dispatch are not
     * replicated here — they are irrelevant to the content routes captured by
     * this runner.
     */
    private static function renderLiveRoute(string $uri): string
    {
        ob_start();
        try {
            $handled = false;
            if ($uri === '/sluzby') {
                $handled = tryServeServicesPage($uri);
            } elseif ($uri === '/nase-prace') {
                $handled = tryServePortfolioPage($uri, false);
            } elseif ($uri === '/blog') {
                $handled = tryServeBlogListingPage();
            } elseif ($uri === '/faq') {
                $handled = tryServeFaqPage();
            } elseif ($uri === '/kontakt') {
                $handled = tryServeKontaktPage();
            }

            if (!$handled) {
                $handled = tryServeDatabasePage($uri, false, false);
            }
            if (!$handled) {
                $handled = tryServeBlogPostPage($uri);
            }
            if (!$handled) {
                $handled = tryServePortfolioPage($uri, true);
            }
            if (!$handled) {
                $notFoundFile = dirname(__DIR__) . '/storage/published/404.html';
                if (is_file($notFoundFile)) {
                    readfile($notFoundFile);
                } else {
                    echo '<!DOCTYPE html><html><body><h1>404 Stranka nenalezena</h1></body></html>';
                }
            }

            return (string) ob_get_clean();
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }

    /** @param array{type: string, page?: array, site: array} $target */
    private static function renderPublishTarget(array $target): string
    {
        $service = new PublishService();

        if ($target['type'] === '404') {
            $service->generate404($target['site']);
            $file = dirname(__DIR__) . '/storage/published/404.html';
        } else {
            $page = $target['page'];
            $result = $service->publishPage((int) $page['id'], self::firstAdminUserId());
            if (empty($result['success'])) {
                throw new RuntimeException(
                    'Publish failed for page #' . $page['id'] . ': ' . ($result['message'] ?? 'unknown error')
                );
            }
            $file = (string) $result['file'];
        }

        $html = file_get_contents($file);
        if ($html === false) {
            throw new RuntimeException('Could not read published file: ' . $file);
        }

        return $html;
    }

    private static function firstAdminUserId(): int
    {
        if (self::$cachedUserId === null) {
            $row = self::firstRow('SELECT id FROM users ORDER BY id ASC LIMIT 1', []);
            if (!$row) {
                throw new RuntimeException('No user found in the database — PublishService::publishPage() requires a user id.');
            }
            self::$cachedUserId = (int) $row['id'];
        }

        return self::$cachedUserId;
    }

    private static function firstRow(string $sql, array $params): ?array
    {
        $stmt = Database::getInstance()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
