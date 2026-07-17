<?php

declare(strict_types=1);

namespace App\View;

/**
 * Renders a PHTML view to a string.
 *
 * render() resolves the view via ViewResolver, creates a ViewContext (bound as
 * `$this` inside the template), and includes the file inside an output buffer
 * with the render data extracted into local scope. If the view declares a
 * layout(), the captured output becomes the implicit `content` slot and the
 * layout view is rendered within the SAME context so its renderSlot() calls and
 * the extracted slot variables both resolve.
 *
 * No eval, no database access — pure rendering from the supplied data.
 */
final class ViewEngine
{
    private ViewResolver $resolver;

    /** Shared render options handed to every ViewContext (editMode, contentBlocks, assetBaseUrl). */
    private array $options;

    public function __construct(ViewResolver $resolver, array $options = [])
    {
        $this->resolver = $resolver;
        $this->options = $options;
    }

    public function resolver(): ViewResolver
    {
        return $this->resolver;
    }

    public function options(): array
    {
        return $this->options;
    }

    /**
     * Return a new engine with additional/overridden render options, sharing the
     * same resolver.
     */
    public function withOptions(array $options): self
    {
        return new self($this->resolver, array_replace($this->options, $options));
    }

    /**
     * Render a view by logical name and return the resulting HTML.
     */
    public function render(string $view, array $data = []): string
    {
        $path = $this->resolver->resolveOrFail($view);

        $context = new ViewContext($this, $this->options, $data);
        $output = $context->includeTemplate($path, $data);

        $layout = $context->declaredLayout();
        if ($layout !== null) {
            if (!$context->hasSlot('content')) {
                $context->setSlot('content', $output);
            }
            $layoutPath = $this->resolver->resolveOrFail($layout);
            // Render the layout in the same context: renderSlot() works, and the
            // captured slots are also exposed as extracted variables.
            $output = $context->includeTemplate($layoutPath, array_merge($data, $context->slots()));
        }

        return $output;
    }
}
