<?php

declare(strict_types=1);

use App\Services\Render\PortfolioCaptionFormatter;

function register_portfolio_caption_formatter_tests(callable $test, callable $assert): void
{
    $test('portfolio lightbox separates author from paragraph copy', static function () use ($assert): void {
        $caption = PortfolioCaptionFormatter::fromHtml(
            '<p><strong>Autor: Zdeněk, fotograf</strong></p><p>U Kamily a Matěje jsem měl k dispozici dvě prostředí.</p><p>Na louce jsme mohli nechat pár volných chvil.</p>',
            500
        );

        $assert($caption['author'] === 'Zdeněk, fotograf');
        $assert($caption['description'] === "U Kamily a Matěje jsem měl k dispozici dvě prostředí.\nNa louce jsme mohli nechat pár volných chvil.");
        $assert(!str_contains($caption['description'], 'Autor:'));
        $assert(!str_contains($caption['description'], 'prostředí.Na'));
    });
}
