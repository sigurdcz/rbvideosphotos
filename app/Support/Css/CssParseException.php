<?php

declare(strict_types=1);

namespace App\Support\Css;

use RuntimeException;

/**
 * Thrown when a stylesheet cannot be parsed with certainty.
 *
 * Subsetting is only safe on input we understand completely, so anything ambiguous -
 * an unterminated string, a stray brace, an unclosed block - has to stop the whole
 * operation rather than produce a plausible-looking but mangled stylesheet.
 */
final class CssParseException extends RuntimeException
{
}
