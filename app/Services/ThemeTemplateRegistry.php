<?php

namespace App\Services;

use App\Core\Logger;

class ThemeTemplateRegistry
{
    private string $rootPath;
    private string $stateFile;

    public function __construct(?string $rootPath = null)
    {
        $this->rootPath = $rootPath ?: dirname(__DIR__, 2) . '/Templates';
        $this->stateFile = dirname(__DIR__, 2) . '/storage/active-theme-key.txt';
    }

    public function all(): array
    {
        if (!is_dir($this->rootPath)) {
            return [];
        }

        $items = [];
        foreach (glob($this->rootPath . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $theme = $this->loadTheme($dir);
            if ($theme !== null) {
                $items[] = $theme;
            }
        }

        usort($items, static function (array $a, array $b): int {
            $orderA = (int)($a['sort_order'] ?? 0);
            $orderB = (int)($b['sort_order'] ?? 0);
            if ($orderA !== $orderB) {
                return $orderA <=> $orderB;
            }

            return strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
        });

        return $items;
    }

    public function find(string $key): ?array
    {
        $key = trim($key);
        if ($key === '') {
            return null;
        }

        foreach ($this->all() as $theme) {
            if (($theme['key'] ?? '') === $key) {
                return $theme;
            }
        }

        return null;
    }

    public function resolveForSite(?array $site, ?string $previewKey = null): ?array
    {
        $previewKey = trim((string)$previewKey);
        if ($previewKey !== '') {
            $theme = $this->find($previewKey);
            Logger::infoTo('theme-debug', 'theme.preview.resolve', 'Preview theme resolution requested', [
                'preview_key' => $previewKey,
                'resolved' => $theme['key'] ?? null,
                'site_id' => $site['id'] ?? null,
                'site_name' => $site['name'] ?? null,
            ]);
            return $theme;
        }

        $activeKey = trim((string)($site['active_theme_key'] ?? ''));
        $activeKeySource = 'db';
        if ($activeKey === '') {
            $activeKey = $this->getPersistedActiveThemeKey();
            $activeKeySource = 'state_file';
        }

        Logger::infoTo('theme-debug', 'theme.resolve.request', 'Theme resolution requested', [
            'site_id' => $site['id'] ?? null,
            'site_name' => $site['name'] ?? null,
            'db_active_theme_key' => trim((string)($site['active_theme_key'] ?? '')) ?: null,
            'state_active_theme_key' => $this->getPersistedActiveThemeKey() ?: null,
            'resolved_active_theme_key' => $activeKey !== '' ? $activeKey : null,
            'active_key_source' => $activeKeySource,
            'preview_key' => null,
        ]);

        if ($activeKey === '') {
            Logger::infoTo('theme-debug', 'theme.resolve.default', 'Theme resolution fell back to default', [
                'site_id' => $site['id'] ?? null,
                'site_name' => $site['name'] ?? null,
                'reason' => 'no active key found',
            ]);
            return null;
        }

        $theme = $this->find($activeKey);
        Logger::infoTo('theme-debug', 'theme.resolve.result', 'Theme resolution completed', [
            'site_id' => $site['id'] ?? null,
            'site_name' => $site['name'] ?? null,
            'resolved_active_theme_key' => $activeKey,
            'theme_found' => $theme !== null,
            'theme_key' => $theme['key'] ?? null,
            'theme_name' => $theme['name'] ?? null,
        ]);
        return $theme;
    }

    public function normalizePageKey(string $urlPath): string
    {
        $path = trim($urlPath, '/');
        return $path === '' ? 'homepage' : $path;
    }

    public function getPersistedActiveThemeKey(): string
    {
        if (!is_file($this->stateFile)) {
            return '';
        }

        $value = trim((string)file_get_contents($this->stateFile));
        return $value !== '' ? $value : '';
    }

    public function persistActiveThemeKey(?string $key): void
    {
        $key = trim((string)$key);
        $dir = dirname($this->stateFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        if ($key === '') {
            if (is_file($this->stateFile)) {
                @unlink($this->stateFile);
            }
            return;
        }

        file_put_contents($this->stateFile, $key, LOCK_EX);
    }

    public function getCssForPage(array $theme, string $pageKey): string
    {
        $pageKey = $this->normalizePageKey($pageKey);
        $css = $this->collectAssets($theme, $pageKey, 'css');

        if (($theme['key'] ?? '') === 'jemna-bezova' && $pageKey === 'homepage') {
            $css = trim($css . "\n\n" . $this->getJemnaBezovaHomepageCssOverrides());
        }

        return $css;
    }

    public function getJsForPage(array $theme, string $pageKey): string
    {
        return $this->collectAssets($theme, $pageKey, 'js');
    }

    public function getSharedComponentCss(): string
    {
        return <<<'CSS'
.theme-shell .theme-main {
  position: relative;
  z-index: 1;
}

.theme-shell .hp-services-grid,
.theme-shell .svc-grid,
.theme-shell .hp-blog-grid,
.theme-shell .bl-grid,
.theme-shell .hp-cenik-grid,
.theme-shell .faq-list,
.theme-shell .hp-contact-grid,
.theme-shell .pt-grid {
  display: grid;
  gap: 24px;
  align-items: stretch;
}

.theme-shell .hp-services-grid,
.theme-shell .svc-grid,
.theme-shell .hp-blog-grid,
.theme-shell .bl-grid,
.theme-shell .hp-cenik-grid,
.theme-shell .hp-contact-grid,
.theme-shell .pt-grid {
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
}

.theme-shell .hp-svc-card,
.theme-shell .svc-card,
.theme-shell .hp-blog-card,
.theme-shell .hp-cenik-card,
.theme-shell .hp-faq-item,
.theme-shell .pt-tile,
.theme-shell .hp-contact-box,
.theme-shell .bl-card,
.theme-shell .card,
.theme-shell .block-item,
.theme-shell .kt-form-block,
.theme-shell .ci-block {
  background: var(--theme-surface, rgba(255,255,255,.04));
  border: 1px solid var(--theme-border, rgba(255,255,255,.08));
  border-radius: 22px;
  box-shadow: var(--theme-shadow, 0 24px 60px rgba(0,0,0,.20));
  overflow: hidden;
  min-width: 0;
}

.theme-shell .hp-svc-card,
.theme-shell .hp-blog-card,
.theme-shell .hp-cenik-card,
.theme-shell .pt-tile,
.theme-shell .hp-contact-box {
  display: flex;
  flex-direction: column;
}

.theme-shell .hp-svc-card:empty,
.theme-shell .hp-blog-card:empty,
.theme-shell .hp-cenik-card:empty,
.theme-shell .hp-faq-item:empty,
.theme-shell .pt-tile:empty {
  display: none;
}

.theme-shell .hp-svc-card__media,
.theme-shell .pt-thumb,
.theme-shell .hp-blog-thumb {
  background: rgba(0,0,0,.22);
  overflow: hidden;
}

.theme-shell .hp-svc-card__media:empty {
  position: relative;
  min-height: 130px;
  background:
    radial-gradient(circle at 22% 20%, rgba(196,150,60,.18), transparent 18%),
    radial-gradient(circle at 78% 18%, rgba(255,255,255,.10), transparent 15%),
    linear-gradient(180deg, rgba(255,255,255,.05), rgba(255,255,255,.01));
}

.theme-shell .hp-svc-card__media:empty::before {
  content: "";
  position: absolute;
  inset: 14px 14px auto auto;
  width: 42px;
  height: 42px;
  border-radius: 999px;
  border: 1px solid rgba(196,150,60,.28);
  background: rgba(0,0,0,.24);
  box-shadow: 0 10px 28px rgba(0,0,0,.24);
}

.theme-shell .hp-svc-card__media:empty::after {
  content: "";
  position: absolute;
  left: 18px;
  bottom: 18px;
  width: 56px;
  height: 3px;
  border-radius: 999px;
  background: linear-gradient(90deg, var(--theme-accent, #c4963c), rgba(255,255,255,.04));
}

.theme-shell .hp-svc-card__media img,
.theme-shell .pt-thumb img,
.theme-shell .hp-blog-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.theme-shell .hp-svc-card__body,
.theme-shell .hp-blog-body,
.theme-shell .hp-cenik-card-body,
.theme-shell .hp-contact-box,
.theme-shell .pt-caption {
  padding: 22px;
}

.theme-shell .hp-svc-card__body {
  display: flex;
  flex-direction: column;
  gap: 12px;
  flex: 1;
}

.theme-shell .hp-svc-card__num,
.theme-shell .hp-cenik-card-price {
  color: var(--theme-accent, #c4963c);
  font-size: .74rem;
  font-weight: 800;
  letter-spacing: .18em;
  text-transform: uppercase;
}

.theme-shell .hp-svc-card__title,
.theme-shell .hp-blog-title,
.theme-shell .hp-cenik-card-title,
.theme-shell .bl-title,
.theme-shell .section-title {
  color: var(--theme-text, #fff);
}

.theme-shell .hp-svc-card__desc,
.theme-shell .hp-blog-excerpt,
.theme-shell .hp-cenik-card-desc,
.theme-shell .section-intro,
.theme-shell .ci-row {
  color: var(--theme-muted, rgba(255,255,255,.68));
}

.theme-shell .hp-svc-card__btn,
.theme-shell .hp-section-cta .btn-secondary,
.theme-shell .eg-reviews__cta .btn-secondary,
.theme-shell .hp-contact-cta .btn-secondary,
.theme-shell .btn-primary,
.theme-shell .btn-secondary,
.theme-shell .btn-success,
.theme-shell .btn-h--gold,
.theme-shell .nav-cta,
.theme-shell .kt-form-btn,
.theme-shell .ci-link-btn,
.theme-shell .btn-maps {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: fit-content;
  max-width: 100%;
  gap: 8px;
  border-radius: 999px;
  padding: 12px 18px;
  border: 1px solid var(--theme-border, rgba(255,255,255,.12));
  text-decoration: none;
  font-weight: 800;
  letter-spacing: .06em;
  text-transform: uppercase;
}

.theme-shell .hp-svc-card__btn,
.theme-shell .hp-section-cta .btn-secondary,
.theme-shell .eg-reviews__cta .btn-secondary,
.theme-shell .hp-contact-cta .btn-secondary,
.theme-shell .btn-primary,
.theme-shell .btn-success,
.theme-shell .btn-h--gold,
.theme-shell .nav-cta,
.theme-shell .kt-form-btn,
.theme-shell .ci-link-btn,
.theme-shell .btn-maps {
  background: linear-gradient(135deg, var(--theme-accent, #c4963c), var(--theme-accent-strong, #9a671f));
  color: #111 !important;
  border-color: transparent;
  box-shadow: 0 16px 34px rgba(196,150,60,.16);
}

.theme-shell .hp-svc-card__btn:hover,
.theme-shell .btn-primary:hover,
.theme-shell .btn-success:hover,
.theme-shell .btn-h--gold:hover,
.theme-shell .nav-cta:hover,
.theme-shell .kt-form-btn:hover,
.theme-shell .ci-link-btn:hover,
.theme-shell .btn-maps:hover {
  transform: translateY(-1px);
  background: linear-gradient(135deg, var(--theme-accent-strong, #9a671f), var(--theme-accent, #c4963c));
  color: #111 !important;
}

.theme-shell .hp-section-cta .btn-secondary:hover,
.theme-shell .eg-reviews__cta .btn-secondary:hover,
.theme-shell .hp-contact-cta .btn-secondary:hover {
  transform: translateY(-1px);
  background: rgba(0,0,0,.34);
  border-color: var(--theme-accent, #c4963c);
  color: var(--theme-accent, #c4963c) !important;
}

/* Homepage section CTA buttons must remain readable on dark sections. */
.theme-shell .hp-section-cta .btn-secondary,
.theme-shell .eg-reviews__cta .btn-secondary,
.theme-shell .hp-contact-cta .btn-secondary {
  color: #fff !important;
}

.theme-shell .hp-cenik-card {
  padding: 30px;
  gap: 20px;
  cursor: pointer;
}

.theme-shell .hp-cenik-card--featured {
  transform: translateY(-2px);
}

.theme-shell .hp-cenik-card-price {
  margin-top: auto;
  padding-top: 18px;
  border-top: 1px solid var(--theme-border, rgba(255,255,255,.08));
  color: var(--theme-text, #fff);
  font-size: 1.25rem;
  font-weight: 900;
}

.theme-shell .hp-cenik-modal {
  position: fixed;
  inset: 0;
  z-index: 9999;
  display: none;
  align-items: center;
  justify-content: center;
  padding: 24px;
  background: rgba(0,0,0,.72);
}

.theme-shell .hp-cenik-modal.is-open {
  display: flex;
}

.theme-shell .hp-cenik-modal__dialog {
  position: relative;
  width: min(560px, 100%);
  padding: 28px;
  border-radius: 24px;
  background: var(--theme-surface-strong, #17171c);
  border: 1px solid var(--theme-border, rgba(255,255,255,.08));
  box-shadow: 0 36px 90px rgba(0,0,0,.5);
}

.theme-shell .hp-cenik-modal__title {
  color: var(--theme-text, #fff);
  font-size: clamp(1.4rem, 3vw, 2rem);
  font-weight: 900;
}

.theme-shell .hp-cenik-modal__plan {
  color: var(--theme-accent, #c4963c);
  font-weight: 800;
  margin-bottom: 20px;
}

.theme-shell .hp-cenik-modal form,
.theme-shell .hp-contact-form {
  display: grid;
  gap: 12px;
}

.theme-shell .hp-cenik-modal label,
.theme-shell .hp-contact-form label {
  font-size: .82rem;
  color: var(--theme-muted, rgba(255,255,255,.68));
  text-transform: uppercase;
  letter-spacing: .04em;
}

.theme-shell .hp-cenik-modal input,
.theme-shell .hp-cenik-modal textarea,
.theme-shell .hp-contact-form input,
.theme-shell .hp-contact-form textarea {
  width: 100%;
  background: rgba(255,255,255,.03);
  color: var(--theme-text, #fff);
  border: 1px solid var(--theme-border, rgba(255,255,255,.12));
  border-radius: 12px;
  padding: 12px 14px;
  font-size: .95rem;
  font-family: inherit;
}

.theme-shell .hp-cenik-modal textarea,
.theme-shell .hp-contact-form textarea {
  min-height: 120px;
  resize: vertical;
}

.theme-shell .hp-cenik-modal__submit {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 14px 18px;
  border-radius: 999px;
  background: linear-gradient(135deg, var(--theme-accent, #c4963c), var(--theme-accent-strong, #9a671f));
  color: #111;
  border: 0;
  font-weight: 800;
  letter-spacing: .06em;
  text-transform: uppercase;
  cursor: pointer;
}

.theme-shell .hp-cenik-modal__submit:hover {
  transform: translateY(-1px);
  background: linear-gradient(135deg, var(--theme-accent-strong, #9a671f), var(--theme-accent, #c4963c));
}

.theme-shell .hp-cenik-modal__close {
  position: absolute;
  top: 12px;
  right: 12px;
  background: transparent;
  border: none;
  color: var(--theme-text, #fff);
  font-size: 2rem;
  line-height: 1;
  cursor: pointer;
}

.theme-shell .pt-grid {
  gap: 28px;
}

.theme-shell .pt-tile {
  display: flex;
  flex-direction: column;
  transition: transform .24s ease, box-shadow .24s ease;
  text-decoration: none;
  color: inherit;
}

.theme-shell .pt-tile:hover {
  transform: translateY(-4px);
  box-shadow: 0 36px 90px rgba(0,0,0,.34);
}

.theme-shell .pt-thumb {
  position: relative;
  aspect-ratio: 4 / 3;
}

.theme-shell .pt-play {
  position: absolute;
  left: 50%;
  top: 50%;
  transform: translate(-50%, -50%);
  width: 60px;
  height: 60px;
  border-radius: 999px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(196,150,60,.94);
  color: #111;
  font-size: 1.1rem;
  box-shadow: 0 16px 34px rgba(196,150,60,.2);
}

.theme-shell .pt-photos {
  display: flex;
  gap: 4px;
  height: 84px;
  padding: 4px;
  background: rgba(255,255,255,.03);
}

.theme-shell .pt-photos img {
  flex: 1;
  min-width: 0;
  height: 100%;
  object-fit: cover;
  display: block;
}

.theme-shell .pt-caption {
  display: flex;
  align-items: center;
  min-height: 68px;
  padding: 16px 18px;
  line-height: 1.35;
  border-bottom: 1px solid var(--theme-border, rgba(255,255,255,.08));
  background: rgba(255,255,255,.02);
  color: var(--theme-text, #fff);
  font-weight: 800;
}

.theme-shell .hp-blog-card {
  overflow: hidden;
}

.theme-shell .hp-blog-card > article.hp-blog-card {
  display: contents;
}

.theme-shell .hp-blog-thumb {
  aspect-ratio: 16 / 10;
}

.theme-shell .hp-blog-body {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.theme-shell .hp-blog-link {
  color: var(--theme-accent, #c4963c);
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: .08em;
  text-decoration: none;
}

.theme-shell .hp-contact-grid {
  gap: 24px;
}

.theme-shell .hp-contact-row {
  display: flex;
  flex-direction: column;
  gap: 4px;
  line-height: 1.55;
}

.theme-shell .hp-contact-row a {
  color: var(--theme-accent, #c4963c);
  text-decoration: none;
}

.theme-shell .hp-contact-label {
  font-size: .78rem;
  color: var(--theme-muted, rgba(255,255,255,.52));
  text-transform: uppercase;
  letter-spacing: .04em;
}

.theme-shell .hp-contact-map-embed {
  width: 100%;
  min-height: 280px;
  border: 0;
  border-radius: 20px;
}

.theme-shell .theme-footer {
  border-top: 1px solid var(--theme-border, rgba(255,255,255,.08));
  background: rgba(0,0,0,.28);
}

.theme-shell .theme-footer__inner {
  min-height: 96px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  padding: 24px 0;
}

.theme-shell .theme-footer__brand {
  color: var(--theme-text, #fff);
  font-weight: 900;
  letter-spacing: .08em;
  text-transform: uppercase;
}

.theme-shell .theme-footer__meta {
  color: var(--theme-muted, rgba(255,255,255,.62));
}

.theme-shell .site-footer-socials {
  margin-top: 24px;
  padding-top: 24px;
  border-top: 1px solid var(--theme-border, rgba(255,255,255,.08));
  display: flex;
  flex-direction: column;
  gap: 14px;
  align-items: center;
}

.theme-shell .site-footer-socials__label {
  font-size: .76rem;
  letter-spacing: .16em;
  text-transform: uppercase;
  color: var(--theme-muted, rgba(255,255,255,.52));
}

.theme-shell .site-footer-socials__list {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 10px;
}

.theme-shell .site-footer-contact__item strong,
.theme-shell .site-footer-contact__item a {
  color: var(--theme-text, #fff);
}

.theme-shell .site-footer-social {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 50px;
  height: 40px;
  padding: 0;
  border-radius: 0;
  background: transparent;
  border: 1px solid rgba(212,176,111,.24);
  color: rgba(255,255,255,.82);
  text-decoration: none;
  transition: transform .2s, background-color .2s, border-color .2s, color .2s;
}

.theme-shell .site-footer-social:hover {
  transform: translateY(-1px);
  background: rgba(212,176,111,.06);
  border-color: rgba(212,176,111,.62);
  color: #f3efe7;
}

.theme-shell .site-footer-social__icon {
  display: grid;
  place-items: center;
  line-height: 1;
  transition: color .2s, transform .2s;
}

.theme-shell .site-footer-social:hover .site-footer-social__icon {
  transform: scale(1.06);
}

.theme-shell .site-footer-social__icon svg {
  width: 16px;
  height: 16px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.75;
  stroke-linecap: round;
  stroke-linejoin: round;
}

@media (max-width: 760px) {
  .theme-shell .hp-services-grid,
  .theme-shell .svc-grid,
  .theme-shell .hp-blog-grid,
  .theme-shell .bl-grid,
  .theme-shell .hp-cenik-grid,
  .theme-shell .hp-contact-grid,
  .theme-shell .pt-grid {
    grid-template-columns: 1fr;
  }

  .theme-shell .theme-footer__inner {
    flex-direction: column;
    align-items: flex-start;
  }

  .theme-shell .hp-contact-grid > :first-child,
  .theme-shell .hp-contact-grid > :last-child {
    order: initial;
  }
}
CSS;
    }

    public function getLayoutHtmlForPage(array $theme, string $pageKey): string
    {
        $pageKey = $this->normalizePageKey($pageKey);
        $candidates = [];

        if (!empty($theme['layout_html'])) {
            $candidates[] = (string)$theme['layout_html'];
        }

        if (!empty($theme['overrides'][$pageKey]['layout_html'])) {
            $candidates[] = (string)$theme['overrides'][$pageKey]['layout_html'];
        }

        foreach ($candidates as $relativePath) {
            $content = $this->readThemeFile($theme, $relativePath);
            if ($content !== '') {
                return $content;
            }
        }

        return '';
    }

    public function getThemeSummary(array $theme): array
    {
        return [
            'key' => (string)($theme['key'] ?? ''),
            'name' => (string)($theme['name'] ?? ''),
            'description' => (string)($theme['description'] ?? ''),
            'version' => (string)($theme['version'] ?? ''),
            'author' => (string)($theme['author'] ?? ''),
            'sort_order' => (int)($theme['sort_order'] ?? 0),
            'pages' => array_values(array_filter((array)($theme['pages'] ?? []), static fn($v) => trim((string)$v) !== '')),
            'asset_css' => count($this->assetList($theme, 'css')),
            'asset_js' => count($this->assetList($theme, 'js')),
            'preview_image' => (string)($theme['preview_image'] ?? ''),
            'active' => !empty($theme['active']),
        ];
    }

    public function injectBodyClass(string $html, string $themeKey): string
    {
        $themeClass = 'theme-' . preg_replace('~[^a-z0-9_-]+~i', '-', trim($themeKey));
        if ($themeClass === 'theme-') {
            return $html;
        }

        return preg_replace_callback('~<body\b([^>]*)>~i', static function (array $matches) use ($themeClass): string {
            $attrs = $matches[1] ?? '';
            if (preg_match('~\bclass=(["\'])(.*?)\1~i', $attrs, $classMatch)) {
                $existing = trim($classMatch[2]);
                $classes = preg_split('~\s+~', $existing, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                if (!in_array($themeClass, $classes, true)) {
                    $classes[] = $themeClass;
                }
                $replacement = 'class="' . htmlspecialchars(implode(' ', $classes), ENT_QUOTES, 'UTF-8') . '"';
                $attrs = preg_replace('~\bclass=(["\'])(.*?)\1~i', $replacement, $attrs, 1);
            } else {
                $attrs .= ' class="' . htmlspecialchars($themeClass, ENT_QUOTES, 'UTF-8') . '"';
            }

            return '<body' . $attrs . '>';
        }, $html, 1) ?? $html;
    }

    private function loadTheme(string $dir): ?array
    {
        $manifestPath = rtrim($dir, '/\\') . '/template.json';
        if (!is_file($manifestPath)) {
            return null;
        }

        $json = json_decode((string)file_get_contents($manifestPath), true);
        if (!is_array($json)) {
            return null;
        }

        $key = trim((string)($json['key'] ?? basename($dir)));
        $name = trim((string)($json['name'] ?? ''));
        if ($key === '' || $name === '') {
            return null;
        }

        $theme = [
            'key' => $key,
            'name' => $name,
            'description' => trim((string)($json['description'] ?? '')),
            'version' => trim((string)($json['version'] ?? '')),
            'author' => trim((string)($json['author'] ?? '')),
            'sort_order' => (int)($json['sort_order'] ?? 0),
            'preview_image' => trim((string)($json['preview_image'] ?? '')),
            'pages' => array_values(array_map('strval', (array)($json['pages'] ?? []))),
            'assets' => [
                'css' => $this->normalizeAssetList($json['assets']['css'] ?? []),
                'js' => $this->normalizeAssetList($json['assets']['js'] ?? []),
            ],
            'layout_html' => trim((string)($json['layout_html'] ?? '')),
            'layout_css' => trim((string)($json['layout_css'] ?? '')),
            'layout_js' => trim((string)($json['layout_js'] ?? '')),
            'overrides' => [],
            'path' => $dir,
        ];

        foreach ((array)($json['overrides'] ?? []) as $pageKey => $override) {
            $pageKey = $this->normalizePageKey((string)$pageKey);
            if ($pageKey === '') {
                continue;
            }

            $theme['overrides'][$pageKey] = [
                'layout_html' => trim((string)($override['layout_html'] ?? '')),
                'css' => $this->normalizeAssetList($override['assets']['css'] ?? $override['css'] ?? []),
                'js' => $this->normalizeAssetList($override['assets']['js'] ?? $override['js'] ?? []),
            ];
        }

        return $theme;
    }

    private function normalizeAssetList(mixed $assets): array
    {
        if (is_string($assets)) {
            $assets = [$assets];
        }

        $items = [];
        foreach ((array)$assets as $asset) {
            $asset = trim((string)$asset);
            if ($asset !== '') {
                $items[] = $asset;
            }
        }

        return array_values(array_unique($items));
    }

    private function assetList(array $theme, string $type): array
    {
        $items = [];

        foreach ($this->normalizeAssetList($theme['assets'][$type] ?? []) as $relativePath) {
            $items[] = $relativePath;
        }

        $pageKey = 'homepage';
        foreach ($this->normalizeAssetList($theme['overrides'][$pageKey]['assets'][$type] ?? $theme['overrides'][$pageKey][$type] ?? []) as $relativePath) {
            $items[] = $relativePath;
        }

        return array_values(array_unique($items));
    }

    private function collectAssets(array $theme, string $pageKey, string $type): string
    {
        $pageKey = $this->normalizePageKey($pageKey);
        $paths = [];

        if (!empty($theme['layout_' . $type])) {
            $paths[] = (string)$theme['layout_' . $type];
        }

        foreach ($this->normalizeAssetList($theme['assets'][$type] ?? []) as $relativePath) {
            $paths[] = $relativePath;
        }

        foreach ($this->normalizeAssetList($theme['overrides'][$pageKey]['assets'][$type] ?? $theme['overrides'][$pageKey][$type] ?? []) as $relativePath) {
            $paths[] = $relativePath;
        }

        if ($pageKey !== 'homepage' && !empty($theme['overrides'][$pageKey]['layout_' . $type])) {
            $paths[] = (string)$theme['overrides'][$pageKey]['layout_' . $type];
        }

        $contents = [];
        foreach (array_values(array_unique($paths)) as $relativePath) {
            $content = $this->readThemeFile($theme, $relativePath);
            if ($content !== '') {
                $contents[] = $content;
            }
        }

        return trim(implode("\n\n", $contents));
    }

    private function getJemnaBezovaHomepageCssOverrides(): string
    {
        return <<<'CSS'
/* Runtime-safe homepage hero overrides. This is appended after saved layout CSS. */
.theme-jemna-bezova .hp-hero {
  text-align: center !important;
  align-items: center !important;
  justify-content: center !important;
}

.theme-jemna-bezova .hp-hero > .container {
  position: static !important;
  display: flex !important;
  width: min(calc(100% - 48px), 760px) !important;
  max-width: 760px !important;
  min-height: auto !important;
  margin-left: auto !important;
  margin-right: auto !important;
  padding-left: 0 !important;
  padding-right: 0 !important;
  flex-direction: column !important;
  align-items: center !important;
  justify-content: center !important;
  text-align: center !important;
}

.theme-jemna-bezova .hp-hero .section-label,
.theme-jemna-bezova .hp-hero h1,
.theme-jemna-bezova .hp-hero .hero-sub,
.theme-jemna-bezova .hp-hero .hero-cta-wrap {
  width: 100% !important;
  margin-left: auto !important;
  margin-right: auto !important;
  text-align: center !important;
  justify-content: center !important;
}

.theme-jemna-bezova .hp-hero .hero-cta-wrap {
  align-items: center !important;
}

@media (max-width: 760px) {
  .theme-jemna-bezova .hp-hero > .container {
    width: min(calc(100% - 32px), 360px) !important;
    max-width: 360px !important;
  }

  .theme-jemna-bezova .hp-hero h1,
  .theme-jemna-bezova .hp-hero .hero-sub {
    max-width: 100% !important;
  }
}
CSS;
    }

    private function readThemeFile(array $theme, string $relativePath): string
    {
        $relativePath = str_replace(['\\', "\0"], ['/', ''], trim($relativePath));
        if ($relativePath === '') {
            return '';
        }

        $root = rtrim((string)($theme['path'] ?? ''), '/\\');
        if ($root === '') {
            return '';
        }

        $fullPath = $root . '/' . ltrim($relativePath, '/');
        $realRoot = realpath($root);
        $realPath = realpath($fullPath);
        if ($realRoot === false || $realPath === false) {
            return is_file($fullPath) ? (string)file_get_contents($fullPath) : '';
        }

        if (strpos($realPath, $realRoot) !== 0 || !is_file($realPath)) {
            return '';
        }

        return (string)file_get_contents($realPath);
    }
}
