<?php

declare(strict_types=1);

namespace App\View;

use InvalidArgumentException;

/**
 * Resolves logical PHTML view names to absolute file paths across an ordered
 * list of search roots (theme roots first, core roots last), enforcing the
 * security contract from TEMPLATE_REFACTOR_PLAN.md section 6:
 *
 *   - view name is validated against ^[a-z0-9][a-z0-9/_-]*$ BEFORE any path
 *     is assembled (no dots at all, so `..` traversal is impossible; no leading
 *     slash, backslash, colon, null byte or uppercase);
 *   - the assembled path is passed through realpath() and must stay contained
 *     inside one of the allowed roots - a resolved path that escapes the root
 *     (e.g. via a symlink) is rejected even if the name looked valid.
 *
 * The resolver never trusts an assembled path without realpath containment.
 */
final class ViewResolver
{
    /** File extension for every view. */
    public const EXTENSION = '.phtml';

    /** Allowed view-name characters. `D` = `$` matches end of string only (blocks trailing newline). */
    private const NAME_PATTERN = '~^[a-z0-9][a-z0-9/_-]*$~D';

    /** @var string[] ordered, normalized (forward-slash) absolute search roots */
    private array $roots;

    /**
     * @param string[] $roots ordered search roots (highest priority first)
     */
    public function __construct(array $roots)
    {
        $normalized = [];
        foreach ($roots as $root) {
            $root = (string) $root;
            if ($root === '') {
                continue;
            }
            $normalized[] = self::normalize($root);
        }
        $this->roots = $normalized;
    }

    /**
     * @return string[] normalized search roots in priority order
     */
    public function roots(): array
    {
        return $this->roots;
    }

    /**
     * Validate a view name against the security contract.
     *
     * @throws InvalidArgumentException when the name could enable traversal or
     *                                  contains disallowed characters.
     */
    public function assertValidName(string $name): void
    {
        if ($name === '') {
            throw new InvalidArgumentException('View name must not be empty.');
        }
        if (strlen($name) > 255) {
            throw new InvalidArgumentException('View name is too long.');
        }
        if (strpos($name, "\0") !== false) {
            throw new InvalidArgumentException('View name contains a null byte.');
        }
        // Redundant with the pattern (dots are not allowed at all) but kept as
        // an explicit, self-documenting traversal guard.
        if (strpos($name, '..') !== false) {
            throw new InvalidArgumentException('View name must not contain "..".');
        }
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw new InvalidArgumentException('View name has invalid characters: ' . $name);
        }
    }

    /**
     * Resolve a view name to an absolute path, or null when no matching file
     * exists inside any allowed root.
     *
     * @throws InvalidArgumentException on an invalid/malicious name (never
     *                                  silently resolves outside a root).
     */
    public function resolve(string $name): ?string
    {
        $this->assertValidName($name);

        foreach ($this->roots as $root) {
            $candidate = $root . '/' . $name . self::EXTENSION;
            $real = realpath($candidate);
            if ($real === false) {
                continue;
            }
            $real = self::toForwardSlash($real);
            // Defense in depth: even a name that passed validation must not
            // resolve (after symlink/realpath canonicalization) outside the root.
            if (!self::isWithin($real, $root)) {
                continue;
            }
            return $real;
        }

        return null;
    }

    /**
     * @throws InvalidArgumentException when the view cannot be resolved.
     */
    public function resolveOrFail(string $name): string
    {
        $path = $this->resolve($name);
        if ($path === null) {
            throw new InvalidArgumentException('View not found: ' . $name);
        }
        return $path;
    }

    private static function normalize(string $path): string
    {
        $real = realpath($path);
        if ($real !== false) {
            $path = $real;
        }
        return rtrim(self::toForwardSlash($path), '/');
    }

    private static function toForwardSlash(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    private static function isWithin(string $path, string $root): bool
    {
        $path = rtrim(self::toForwardSlash($path), '/');
        $root = rtrim(self::toForwardSlash($root), '/');

        // Windows paths are case-insensitive; compare accordingly so drive-letter
        // / disk casing differences never produce a false "escaped root".
        if (DIRECTORY_SEPARATOR === '\\') {
            $path = strtolower($path);
            $root = strtolower($root);
        }

        return $path === $root || str_starts_with($path, $root . '/');
    }
}
