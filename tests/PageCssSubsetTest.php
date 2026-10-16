<?php

declare(strict_types=1);

use App\Support\Css\CssParseException;
use App\Support\Css\CssParser;
use App\Support\Css\PageCssPruner;
use App\Support\Css\SelectorTokenExtractor;
use App\Support\Css\TokenUniverse;
use App\Support\PageCssSubsetter;

function register_page_css_subset_tests(callable $test, callable $assert): void
{
    $root = dirname(__DIR__);

    // The generated bundle lives in a gitignored directory, so the parser is exercised
    // against the committed theme stylesheet it is built from - always present, and the
    // largest piece of real CSS in the repository.
    $realCss = static function () use ($root): string {
        return (string) file_get_contents($root . '/Templates/golden-bash/assets/theme.css');
    };

    $test('css parser round-trips real theme css byte for byte', function () use ($assert, $realCss): void {
        // The predecessor of this code sliced Chrome coverage offsets, which silently
        // dropped every @media wrapper. Byte identity is the guard against that class of bug.
        //
        // Ordinary comments are dropped by design, so identity is asserted from the first
        // serialization onwards - which is the form the subsetter ever actually handles,
        // since the bundle it reads is already minified.
        $css = $realCss();
        $assert($css !== '', 'The theme stylesheet must be readable.');

        $once = CssParser::serialize(CssParser::parse($css));
        $assert(CssParser::isLossless($once), 'Parser must reproduce a stylesheet exactly.');
        $assert(
            substr_count($once, '@media') === substr_count($css, '@media'),
            'Every @media wrapper must survive a parse/serialize cycle.'
        );
        $assert(str_contains($once, '@media'), 'The theme stylesheet is expected to contain media queries.');
    });

    $test('pruning keeps every rule when the page uses every token', function () use ($assert, $realCss): void {
        $css = CssParser::serialize(CssParser::parse($realCss()));
        $universe = [];
        foreach (PageCssPruner::selectorTokens($css) as $token) {
            $universe[$token] = true;
        }
        $assert(PageCssPruner::prune($css, $universe) === $css, 'A full universe must prune nothing.');
    });

    $test('pruning keeps context-free rules and at-rules when the page uses nothing', function () use ($assert): void {
        $css = '@keyframes spin{from{opacity:0}}@font-face{font-family:X;src:url(x.woff2)}'
            . 'html{color:red}.used{color:blue}.unused{color:green}'
            . '@media(max-width:700px){.used{color:teal}.unused{color:pink}}';
        $pruned = PageCssPruner::prune($css, ['used' => true]);

        $assert(str_contains($pruned, '@keyframes spin'), 'Keyframes cannot be reasoned about and must stay.');
        $assert(str_contains($pruned, '@font-face'), 'Font faces must stay.');
        $assert(str_contains($pruned, 'html{color:red}'), 'Rules without class or id requirements must stay.');
        $assert(str_contains($pruned, '.used{color:blue}'), 'Used rules must stay.');
        $assert(!str_contains($pruned, '.unused{color:green}'), 'Unused rules must go.');
        $assert(str_contains($pruned, '@media(max-width:700px){.used{color:teal}}'),
            'A surviving rule must keep its media wrapper and lose only its unused siblings.');
    });

    $test('empty media blocks disappear but nested ones keep their context', function () use ($assert): void {
        $css = '@media print{.gone{color:red}}@media screen{@supports (display:grid){.kept{display:grid}}}';
        $pruned = PageCssPruner::prune($css, ['kept' => true]);
        $assert(!str_contains($pruned, '@media print'), 'A media block with no survivors is dead weight.');
        $assert($pruned === '@media screen{@supports (display:grid){.kept{display:grid}}}',
            'Nested at-rules must be rebuilt exactly.');
    });

    $test('the parser is not fooled by braces inside strings, urls or comments', function () use ($assert): void {
        $css = '.a{content:"}"}.b{background:url(data:image/svg+xml;base64,ab}cd)}/* } */.c{color:red}';
        $nodes = CssParser::parse($css);
        $assert(count($nodes) === 3, 'Braces inside strings, urls and comments must not split rules.');
        $assert(CssParser::serialize($nodes) === str_replace('/* } */', '', $css), 'Only plain comments are dropped.');
    });

    $test('a quoted url() may contain the closing paren', function () use ($assert): void {
        // A data URI holding an SVG transform - rotate(45) - is the everyday form of this.
        $css = '.a{background:url("x)y")}.b{color:red}';
        $assert(CssParser::isLossless($css), 'A quoted url() must not swallow the rules after it.');
        $assert(count(CssParser::parse($css)) === 2, 'Both rules must survive.');
    });

    $test('malformed css is refused rather than silently truncated', function () use ($assert): void {
        // Each of these used to produce a shorter, plausible-looking stylesheet. Smaller is
        // exactly what a successful subset looks like, so a wrong result could not be told
        // apart from a right one - it has to fail loudly instead.
        $cases = [
            'stray closing brace'      => '.a{color:red}}.b{color:blue}',
            'unterminated string'      => '.a{content:"x}.b{color:red}',
            'unclosed rule'            => '.a{color:red',
            'unterminated comment'     => '/* x',
            'unterminated url'         => '.a{background:url(nope',
            'unclosed media block'     => '@media screen{.a{color:red}',
        ];
        foreach ($cases as $label => $css) {
            $threw = false;
            try {
                CssParser::parse($css);
            } catch (CssParseException) {
                $threw = true;
            }
            $assert($threw, 'Malformed css must be refused: ' . $label);
            $assert(!CssParser::isLossless($css), 'Malformed css is never lossless: ' . $label);
        }
    });

    $test('selector requirements resolve to keep whenever they are ambiguous', function () use ($assert): void {
        $universe = ['known' => true];
        $keep = [
            'html', '*', 'body a', 'a[href]', '.known:hover', '.unknown, .known',
            // Attribute values are strings, not class or id references.
            'a[href$=".pdf"]', '.known[data-v="#top"]', '[data-v=1.5]',
            // A selector list inside a functional pseudo needs only one alternative to match.
            '.known:has(.known,.unknown)', '.known:nth-child(2n+1 of .unknown)',
            // An escaped identifier cannot be decoded reliably, so it states no requirement.
            '.md\\:flex',
        ];
        foreach ($keep as $selector) {
            $assert(SelectorTokenExtractor::matches($selector, $universe), 'Must keep: ' . $selector);
        }
        foreach (['.unknown', '.known .unknown'] as $selector) {
            $assert(!SelectorTokenExtractor::matches($selector, $universe), 'Must drop: ' . $selector);
        }
        // :not/:is/:where never make a token mandatory.
        $assert(SelectorTokenExtractor::matches('.known:not(.unknown)', $universe), ':not must not create a requirement.');
        $assert(SelectorTokenExtractor::matches(':is(.unknown,.known) .known', $universe), ':is must not create a requirement.');
    });

    $test('the page scope attribute is a requirement, other attributes are not', function () use ($assert, $root): void {
        // 341 theme rules scope themselves with [data-cms-page-key="..."]. The key is
        // written once on <body> and never rewritten, so a page with a different key
        // provably cannot match them - this is what makes the subset worth having.
        $homepage = SelectorTokenExtractor::attributeToken('data-cms-page-key', 'homepage');
        $universe = ['hp-hero' => true, $homepage => true];

        $assert(
            SelectorTokenExtractor::matches('[data-cms-page-key="homepage"] .hp-hero', $universe),
            'The page that carries the key must keep its own rules.'
        );
        $assert(
            !SelectorTokenExtractor::matches('[data-cms-page-key="cenik"] .hp-hero', $universe),
            'Another page cannot match a rule scoped to a different key.'
        );
        // Anything a script could set later must never be treated as a requirement.
        $assert(
            SelectorTokenExtractor::matches('[aria-expanded="true"] .hp-hero', $universe),
            'A runtime attribute must not make a rule droppable.'
        );

        $found = TokenUniverse::fromHtml('<body data-cms-page-key="cenik">', $root . '/public');
        $assert(
            isset($found[SelectorTokenExtractor::attributeToken('data-cms-page-key', 'cenik')]),
            'The universe must record the page key the document carries.'
        );
    });

    $test('the universe reads class attributes in every quoting style', function () use ($assert): void {
        $html = '<i class=\'a b\'><i id=c><i class = "e">';
        $tokens = TokenUniverse::tokensOf($html);
        foreach (['a', 'b', 'c', 'e'] as $token) {
            $assert(isset($tokens[$token]), 'Attribute token missing from the universe: ' . $token);
        }
    });

    $test('runtime class names reach the universe through the theme scripts', function () use ($assert, $root): void {
        $js = (string) file_get_contents($root . '/Templates/golden-bash-diagonal/assets/theme.js');
        $tokens = TokenUniverse::tokensOfScript($js);
        // These are applied only after load, so the markup alone never mentions them.
        foreach (['gb-button', 'is-playing', 'gb-nav-open', 'homepage-portfolio-grid'] as $token) {
            $assert(isset($tokens[$token]), 'Runtime token missing from the universe: ' . $token);
        }

        // Shapes other than classList.add(): a class applied through any of these is just
        // as invisible in the markup, and missing one drops the rule that styles it.
        $shapes = TokenUniverse::tokensOfScript(
            'el.className += " appended"; el.classList["add"]("bracketed");'
            . 'el.innerHTML = "<b class=\\"embedded\\">";'
        );
        foreach (['appended', 'bracketed', 'embedded'] as $token) {
            $assert(isset($shapes[$token]), 'Script token missing from the universe: ' . $token);
        }
    });

    $test('an unreadable script aborts subsetting instead of guessing', function () use ($assert, $root): void {
        $html = '<script src="/assets/does-not-exist.js?v=2"></script>';
        $threw = false;
        try {
            TokenUniverse::fromHtml($html, $root . '/public');
        } catch (RuntimeException) {
            $threw = true;
        }
        $assert($threw, 'A script that cannot be read leaves the universe incomplete.');
    });

    $test('subsetting leaves markup untouched when it cannot help', function () use ($assert): void {
        $html = '<html><head></head><body>x</body></html>';
        $assert(PageCssSubsetter::apply($html) === $html, 'No stylesheet link means no rewrite.');

        $missing = '<link rel="stylesheet" href="/assets/generated/page-css-deadbeef.css" data-generated-page-css>';
        $assert(PageCssSubsetter::apply($missing) === $missing, 'A missing bundle must be left alone.');
    });
}
