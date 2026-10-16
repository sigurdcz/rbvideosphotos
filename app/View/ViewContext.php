<?php

declare(strict_types=1);

namespace App\View;

use LogicException;

/**
 * The `$this` object available inside every rendered PHTML view.
 *
 * A ViewContext is created per top-level render (and per partial). It exposes
 * the authoring API described in TEMPLATE_REFACTOR_PLAN.md section 2.5 and owns
 * the slot buffers + declared layout for the view it renders.
 *
 * This layer performs NO database access. All content arrives via the render
 * data array and the shared render options (editMode, contentBlocks, …).
 */
final class ViewContext
{
    private ViewEngine $engine;

    /** Shared render options: editMode(bool), contentBlocks(array), assetBaseUrl(string). */
    private array $options;

    /** Data bound to this specific view render. */
    private array $data;

    /** Captured slot contents: name => html. */
    private array $slots = [];

    /** Stack of slot names currently being captured (supports nesting). */
    private array $slotStack = [];

    /** Layout view name declared via layout(); null = render standalone. */
    private ?string $layout = null;

    public function __construct(ViewEngine $engine, array $options, array $data)
    {
        $this->engine = $engine;
        $this->options = $options;
        $this->data = $data;
    }

    // ------------------------------------------------------------------
    // Escaping
    // ------------------------------------------------------------------

    /**
     * Escape a value for HTML output. This is the default, safe path.
     */
    public function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Emit a value WITHOUT escaping. Only for trusted DB HTML blocks that are
     * sanitized elsewhere in the pipeline (sanitizeFrontendHtml).
     */
    public function raw(mixed $html): string
    {
        return (string) $html;
    }

    // ------------------------------------------------------------------
    // Partials
    // ------------------------------------------------------------------

    /**
     * Render a partial through the same resolution cascade and return its HTML.
     * The partial receives only the explicitly passed data (isolated scope).
     */
    public function partial(string $name, array $data = []): string
    {
        return $this->engine->render($name, $data);
    }

    // ------------------------------------------------------------------
    // Content blocks (injected as data - never a DB query from a view)
    // ------------------------------------------------------------------

    /**
     * Return the first non-empty content block among $key and $fallbackKeys.
     * Reads from the `contentBlocks` map in the shared render options.
     * Returns the raw stored content (blocks hold DB HTML fragments).
     */
    public function block(string $key, string ...$fallbackKeys): string
    {
        $blocks = $this->options['contentBlocks'] ?? [];
        if (!is_array($blocks)) {
            return '';
        }

        foreach (array_merge([$key], $fallbackKeys) as $candidate) {
            if (!array_key_exists($candidate, $blocks)) {
                continue;
            }
            $entry = $blocks[$candidate];
            $content = is_array($entry) ? ($entry['content'] ?? '') : $entry;
            if ($content !== null && $content !== '') {
                return (string) $content;
            }
        }

        return '';
    }

    // ------------------------------------------------------------------
    // Inline editor attributes
    // ------------------------------------------------------------------

    /**
     * Emit ` data-inline-key="…" data-inline-type="…"` - but ONLY when edit
     * mode is active in the shared options. Otherwise returns an empty string.
     * Mirrors the output of the legacy inlineAttrs() helper so the inline editor
     * keeps working unchanged. Type defaults to "text".
     */
    /**
     * True when the page is rendered inside the admin inline editor.
     * Templates use it to keep collapsible content expanded while editing.
     */
    public function isEditMode(): bool
    {
        return !empty($this->options['editMode']);
    }

    public function inlineAttr(string $key, ?string $type = null): string
    {
        if (empty($this->options['editMode']) || $key === '') {
            return '';
        }

        $type ??= 'text';

        return ' data-inline-key="' . $this->e($key) . '" data-inline-type="' . $this->e($type) . '"';
    }

    // ------------------------------------------------------------------
    // Slots
    // ------------------------------------------------------------------

    /**
     * Begin capturing a named slot. Everything echoed until endSlot() is stored
     * under $name instead of being emitted inline.
     */
    public function slot(string $name): void
    {
        $this->slotStack[] = $name;
        ob_start();
    }

    /**
     * Finish capturing the most recently opened slot.
     */
    public function endSlot(): void
    {
        if ($this->slotStack === []) {
            throw new LogicException('endSlot() called without a matching slot().');
        }
        $name = array_pop($this->slotStack);
        $this->slots[$name] = (string) ob_get_clean();
    }

    /**
     * Output a captured slot's content (used inside a layout view).
     */
    public function renderSlot(string $name, string $default = ''): string
    {
        return $this->slots[$name] ?? $default;
    }

    public function hasSlot(string $name): bool
    {
        return array_key_exists($name, $this->slots);
    }

    // ------------------------------------------------------------------
    // Layout
    // ------------------------------------------------------------------

    /**
     * Declare which layout view wraps this view (call with a name), or read the
     * currently declared layout (call with no argument).
     */
    public function layout(?string $name = null): ?string
    {
        if ($name !== null) {
            // Validate early so a compromised name can never reach path assembly.
            $this->engine->resolver()->assertValidName($name);
            $this->layout = $name;
        }

        return $this->layout;
    }

    // ------------------------------------------------------------------
    // Assets & media
    // ------------------------------------------------------------------

    /**
     * Build a URL to a theme/core asset. Absolute URLs and root-relative paths
     * pass through untouched; relative paths are prefixed with the configured
     * asset base (root-relative, never domain-qualified - INSTRUCTIONS.md).
     */
    public function asset(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }
        if (preg_match('~^(?:https?:)?//~i', $path) === 1 || $path[0] === '/') {
            return $path;
        }

        $base = rtrim((string) ($this->options['assetBaseUrl'] ?? ''), '/');

        return ($base === '' ? '' : $base) . '/' . ltrim($path, '/');
    }

    /**
     * Normalize a stored media path to a root-relative /media/... URL. Wraps the
     * canonical global publicMediaUrl() when it is loaded (live/publish request),
     * and falls back to a byte-identical local copy when it is not (isolated
     * rendering / tests).
     */
    public function mediaUrl(string $path): string
    {
        if (function_exists('publicMediaUrl')) {
            return publicMediaUrl($path);
        }

        return self::normalizeMediaUrl($path);
    }

    // ------------------------------------------------------------------
    // Engine-facing internals
    // ------------------------------------------------------------------

    /** @internal Layout declared by the rendered view, if any. */
    public function declaredLayout(): ?string
    {
        return $this->layout;
    }

    /** @internal All captured slots. */
    public function slots(): array
    {
        return $this->slots;
    }

    /** @internal Seed a slot (used by the engine for the implicit `content` slot). */
    public function setSlot(string $name, string $content): void
    {
        $this->slots[$name] = $content;
    }

    /**
     * @internal Include a resolved template file with $this bound to this
     * context. Uses ob_start + extract(EXTR_SKIP) + include (never eval). The
     * closure is defined inside this method, so $this inside the included file
     * is this ViewContext; ->call($this) makes that binding explicit.
     */
    public function includeTemplate(string $__file, array $__data): string
    {
        $__level = ob_get_level();

        $__render = function () use ($__file, $__data): void {
            // EXTR_SKIP: incoming data can never clobber $__file/$__data/$__level.
            extract($__data, EXTR_SKIP);
            include $__file;
        };

        ob_start();
        try {
            $__render->call($this);
        } catch (\Throwable $e) {
            while (ob_get_level() > $__level) {
                ob_end_clean();
            }
            throw $e;
        }

        return (string) ob_get_clean();
    }

    /**
     * Byte-for-byte mirror of public/index.php publicMediaUrl(). Kept private so
     * the view layer stays self-contained when the global function is absent.
     */
    private static function normalizeMediaUrl(string $storagePath): string
    {
        $normalized = str_replace('\\', '/', trim($storagePath));
        if ($normalized === '') {
            return '';
        }

        if (preg_match('~https?://[^/]+(/storage/media/.*)$~i', $normalized, $m)) {
            return '/media/' . ltrim(substr($m[1], strlen('/storage/media/')), '/');
        }

        if (str_starts_with($normalized, '/media/')) {
            return $normalized;
        }

        if (str_starts_with($normalized, '/storage/media/')) {
            return '/media/' . ltrim(substr($normalized, strlen('/storage/media/')), '/');
        }

        if (str_starts_with($normalized, 'storage/media/')) {
            return '/media/' . ltrim(substr($normalized, strlen('storage/media/')), '/');
        }

        return '/' . ltrim($normalized, '/');
    }
}
