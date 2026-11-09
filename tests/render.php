<?php

declare(strict_types=1);

// Render-level checks. Unlike tests/smoke.php, these assertions run on a fresh
// render so they exercise inline_css itself rather than the committed dist/.
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/src/theme.php';

use Twig\Environment;
use Twig\Extra\CssInliner\CssInlinerExtension;
use Twig\Loader\ArrayLoader;
use Twig\Loader\FilesystemLoader;

$root = dirname(__DIR__);
$errors = [];
$fixture = json_decode((string) file_get_contents($root . '/src/fixtures/demo.json'), true, 512, JSON_THROW_ON_ERROR);
$css = (string) file_get_contents($root . '/src/templates/styles/email.css');

/** One environment per theme: Twig globals are fixed once the environment is used. */
$makeTwig = static function (?string $overrides) use ($root, $css): Environment {
    $theme = actual_emails_theme($css, $overrides);
    $twig = new Environment(new FilesystemLoader($root . '/src/templates'), [
        'strict_variables' => true,
        // Same strategy as bin/build.php: HTML templates escaped, text plain.
        'autoescape' => 'name',
        'cache' => false,
    ]);
    $twig->addExtension(new CssInlinerExtension());
    $twig->addGlobal('theme', $theme['tokens']);
    $twig->addGlobal('email_css', $theme['css']);
    return $twig;
};
$twig = $makeTwig(null);
$themeTwigs = ['' => $twig];
foreach (glob($root . '/src/templates/styles/themes/*.css') ?: [] as $themePath) {
    $themeTwigs[basename($themePath, '.css')] = $makeTwig((string) file_get_contents($themePath));
}

// Themes may only redefine existing tokens inside :root.
$themeRejects = [
    'unknown token' => ':root { --acent: #000000; }',
    'extra rule' => ':root { --accent: #000000; } .email-card { border: 0; }',
];
foreach ($themeRejects as $case => $overrides) {
    try {
        actual_emails_theme($css, $overrides);
        $errors[] = "theme resolver accepted a theme with an $case";
    } catch (RuntimeException) {
    }
}
// Shapes are tokens: a theme must be able to square every corner.
$square = actual_emails_theme($css, ':root { --radius-card: 0px; --radius-box: 0px; --radius-control: 0px; --radius-mark: 0px; }');
if (preg_match('/border-radius:\s*(?!0px)[0-9]/', $square['css'])) {
    $errors[] = 'a border-radius in email.css does not come from a radius token';
}

/**
 * Splits the top-level @media blocks of a stylesheet, braces included.
 *
 * @return list<array{prelude: string, body: string}>
 */
function media_blocks(string $stylesheet): array
{
    $blocks = [];
    $offset = 0;
    while (($start = strpos($stylesheet, '@media', $offset)) !== false) {
        $open = strpos($stylesheet, '{', $start);
        if ($open === false) {
            break;
        }
        $depth = 1;
        $cursor = $open + 1;
        while ($cursor < strlen($stylesheet) && $depth > 0) {
            if ($stylesheet[$cursor] === '{') {
                $depth++;
            } elseif ($stylesheet[$cursor] === '}') {
                $depth--;
            }
            $cursor++;
        }
        $blocks[] = [
            'prelude' => trim(substr($stylesheet, $start + 6, $open - $start - 6)),
            'body' => substr($stylesheet, $open + 1, $cursor - $open - 2),
        ];
        $offset = $cursor;
    }
    return $blocks;
}

/** Normalises a CSS colour so attribute and stylesheet values can be compared. */
function normalise_colour(string $value): ?string
{
    $value = strtolower(trim($value));
    if (!preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/', $value, $match)) {
        return null;
    }
    if (strlen($match[1]) === 3) {
        return '#' . $match[1][0] . $match[1][0] . $match[1][1] . $match[1][1] . $match[1][2] . $match[1][2];
    }
    return $value;
}

// ---------------------------------------------------------------------------
// 1. Inliner fidelity. The inliner parses the document with DOMDocument, so it
//    silently re-encodes entities. The preheader filler (layout.html.twig)
//    depends on &#847;&zwnj;&nbsp; staying usable; the test pins the actual
//    behaviour instead of guessing what the next dependency update will do.
// ---------------------------------------------------------------------------
$probe = <<<'TWIG'
{% apply inline_css('.probe { color: #123456; } .probe a { text-decoration: none; }') %}
<!doctype html>
<html><head><title>probe</title>
<style type="text/css">@media screen and (max-width: 620px) { .probe { margin: 0 !important; } }</style>
</head>
<body><div class="probe">
<p id="text">Café — “quotes” and ‘single’ … ©</p>
<p id="angle">Angle &lt;&lt;LCG&gt;&gt;</p>
<p id="amp">Tom &amp; Jerry&nbsp;end</p>
<p id="link"><a href="https://example.org/x?a=1&amp;b=2">link&amp;query</a></p>
<p id="invisible">&#847;&zwnj;&nbsp;</p>
</div></body></html>
{% endapply %}
TWIG;

$probeTwig = new Environment(new ArrayLoader(['probe.html.twig' => $probe]), [
    'strict_variables' => true,
    'autoescape' => 'name',
    'cache' => false,
]);
$probeTwig->addExtension(new CssInlinerExtension());
$probeHtml = $probeTwig->render('probe.html.twig');

$inner = static function (string $id) use ($probeHtml): ?string {
    return preg_match('~<p id="' . $id . '"[^>]*>(.*?)</p>~s', $probeHtml, $match) ? $match[1] : null;
};

$fidelity = [
    // Multi-byte text and typographic punctuation must survive byte-for-byte.
    'text' => 'Café — “quotes” and ‘single’ … ©',
    // Text angle brackets stay escaped, and are not double-escaped.
    'angle' => 'Angle &lt;&lt;LCG&gt;&gt;',
    // &amp; stays an entity; &nbsp; is decoded to U+00A0.
    'amp' => 'Tom &amp; Jerry' . "\u{00A0}" . 'end',
    // A query string must keep &amp;, not become a bare &.
    'link' => '<a href="https://example.org/x?a=1&amp;b=2" style="text-decoration: none;">link&amp;query</a>',
    // Invisible spacer characters are decoded to raw UTF-8; no entity remains.
    'invisible' => "\u{034F}\u{200C}\u{00A0}",
];
foreach ($fidelity as $id => $expected) {
    $actual = $inner($id);
    if ($actual !== $expected) {
        $errors[] = sprintf('inliner probe #%s: expected %s, got %s', $id, json_encode($expected, JSON_UNESCAPED_UNICODE), json_encode($actual, JSON_UNESCAPED_UNICODE));
    }
}
if (str_contains($probeHtml, '&#847;')) {
    $errors[] = 'inliner probe left &#847; as an entity; preheader filler behaviour changed';
}
if (!str_contains($probeHtml, 'color: #123456') || !str_contains($probeHtml, 'text-decoration: none')) {
    $errors[] = 'inliner probe did not inline the supplied stylesheet';
}
// Media-query rules in the document must not be promoted to style attributes.
if (preg_match('~<div class="probe" style="[^"]*margin~', $probeHtml)) {
    $errors[] = 'inliner probe inlined a media-query declaration';
}
if (!str_contains($probeHtml, '@media screen and (max-width: 620px)') || !str_contains($probeHtml, 'margin: 0 !important')) {
    $errors[] = 'inliner probe dropped the document media query';
}
if (stripos(ltrim($probeHtml), '<!doctype html>') !== 0) {
    $errors[] = 'inliner probe rewrote the doctype';
}

// ---------------------------------------------------------------------------
// 2. Media queries and 3. Outlook bgcolor agreement, per message and theme.
// ---------------------------------------------------------------------------
$renderCases = [];
foreach ($themeTwigs as $themeName => $themeTwig) {
    foreach ($fixture['messages'] as $messageName => $message) {
        $renderCases[$messageName . ($themeName === '' ? '' : "--$themeName")] = [$themeTwig, $messageName, array_replace_recursive($message, $fixture['themes'][$themeName] ?? [])];
    }
}
foreach ($renderCases as $name => [$caseTwig, $messageName, $message]) {
    $data = array_replace_recursive($fixture, $message);
    unset($data['messages'], $data['themes']);
    $html = $caseTwig->render('export.html.twig', $data + ['template' => 'messages/' . $messageName . '.html.twig']);

    $document = new DOMDocument();
    libxml_use_internal_errors(true);
    if (!$document->loadHTML($html)) {
        $errors[] = "$name: rendered HTML is not parseable";
        libxml_clear_errors();
        continue;
    }
    libxml_clear_errors();
    $xpath = new DOMXPath($document);

    // Media queries must stay in exactly one <head> style block.
    $styleBlocks = $xpath->query('//head/style');
    if ($styleBlocks->length !== 1) {
        $errors[] = "$name: expected one <style> block in head, found {$styleBlocks->length}";
    } elseif ($xpath->query('//body//style')->length !== 0) {
        $errors[] = "$name: <style> block moved into the body";
    }
    $styleText = $styleBlocks->length === 1 ? $styleBlocks->item(0)->textContent : '';
    // iOS auto-links dates and addresses in blue; the reset must survive.
    if ($styleText !== '' && !str_contains($styleText, 'x-apple-data-detectors')) {
        $errors[] = "$name: missing the iOS data-detector reset in the style block";
    }

    $blocks = media_blocks($styleText);
    if (count($blocks) !== 2) {
        $errors[] = "$name: expected 2 media queries, found " . count($blocks);
    }
    foreach ($blocks as $block) {
        if (!str_contains($block['body'], '!important')) {
            $errors[] = "$name: media query '$block[prelude]' lost its !important declarations";
        }
        if (preg_match('/var\(--/', $block['body'])) {
            $errors[] = "$name: media query '$block[prelude]' contains an unresolved var() token";
        }
    }
    $preludes = array_column($blocks, 'prelude');
    foreach (['screen and (max-width: 620px)', '(prefers-color-scheme: dark)'] as $expected) {
        if (!in_array($expected, $preludes, true)) {
            $errors[] = "$name: missing media query $expected";
        }
    }

    // An @media rule cannot live in a style attribute; a leak means inlining
    // rewrote the stylesheet instead of leaving it in the head.
    foreach ($xpath->query('//*[@style]') as $element) {
        if (str_contains($element->getAttribute('style'), '@media')) {
            $errors[] = "$name: @media leaked into a style attribute";
            break;
        }
    }

    // Word-based Outlook ignores media queries, so the conditional desktop
    // wrapper must stay at the container width. The two values live in
    // different files; drift breaks Outlook silently.
    $container = $xpath->query('//table[contains(concat(" ", normalize-space(@class), " "), " email-container ")]')->item(0);
    preg_match('/max-width:\s*(\d+)px/', $container?->getAttribute('style') ?? '', $containerWidth);
    if (!isset($containerWidth[1])) {
        $errors[] = "$name: no max-width on .email-container";
    } else {
        preg_match_all('/<!--\[if mso\]><table[^>]*width="(\d+)"/', $html, $msoWidths);
        if ($msoWidths[1] === []) {
            $errors[] = "$name: missing the conditional MSO wrapper table";
        } elseif ($msoWidths[1][0] !== $containerWidth[1]) {
            $errors[] = "$name: MSO wrapper width {$msoWidths[1][0]} disagrees with container max-width {$containerWidth[1]}";
        }
    }

    // Outlook Word ignores padding on div and p; spacing must come from a td,
    // a margin, or the spacer component.
    foreach (['div', 'p'] as $tag) {
        foreach ($xpath->query('//' . $tag . '[@style]') as $element) {
            if (preg_match('/(?:^|;)\s*padding[a-z-]*\s*:/', strtolower($element->getAttribute('style')))) {
                $class = trim($element->getAttribute('class'));
                $errors[] = "$name: <$tag" . ($class !== '' ? " class=\"$class\"" : '') . '> has inline padding, ignored by Outlook Word';
            }
        }
    }

    // iOS Apple Mail truncates long messages with "download remaining".
    if (strlen($html) > 20000) {
        $errors[] = sprintf('%s: rendered HTML is %d bytes, over the 20 KB iOS partial-download threshold', $name, strlen($html));
    }

    // Outlook Word ignores inline CSS and reads bgcolor; every bgcolor must
    // therefore match the resolved background-color of the same element.
    foreach ($xpath->query('//*[@bgcolor]') as $element) {
        $class = trim($element->getAttribute('class'));
        $label = $element->nodeName . ($class !== '' ? '.' . implode('.', preg_split('/\s+/', $class)) : '');
        $expected = normalise_colour($element->getAttribute('bgcolor'));
        if ($expected === null) {
            $errors[] = "$name: $label has a non-hex bgcolor";
            continue;
        }
        $style = strtolower($element->getAttribute('style'));
        if (!preg_match('/(?:^|;)\s*background-color\s*:\s*([^;]+)/', $style, $match)) {
            $errors[] = "$name: $label has bgcolor but no inline background-color for other clients";
            continue;
        }
        $actual = normalise_colour($match[1]);
        if ($actual === null) {
            $errors[] = "$name: $label inline background-color '$match[1]' is not a hex colour";
        } elseif ($actual !== $expected) {
            $errors[] = "$name: $label bgcolor $expected disagrees with background-color $actual";
        }
    }
}

// Required, translated fallback copy must fail closed when a caller omits it.
try {
    $twig->render('components/subcopy.html.twig', ['url' => 'https://example.org/action']);
    $errors[] = 'subcopy accepted a missing label under strict_variables';
} catch (\Twig\Error\RuntimeError $error) {
    if (!str_contains($error->getMessage(), 'label')) {
        throw $error;
    }
}
$fallback = $twig->render('components/subcopy.html.twig', [
    'url' => 'https://example.org/action?a=1&b=2',
    'label' => 'Copiez ce lien <test> & continuez :',
]);
if (!str_contains($fallback, 'Copiez ce lien &lt;test&gt; &amp; continuez :') || str_contains($fallback, 'If the button')) {
    $errors[] = 'subcopy did not preserve and escape the supplied translation';
}

// Lists without times use the full width; mixed lists retain aligned columns.
$listCases = [
    'untimed' => [[['title' => 'Update'], ['title' => 'Reminder', 'time' => '', 'subtitle' => ''], ['title' => 'Notice', 'time' => null]], 1, 0],
    'mixed' => [[['title' => 'Update'], ['title' => 'Meeting', 'time' => '09:00', 'subtitle' => 'Online']], 2, 1],
    'timed' => [[['title' => 'Meeting', 'time' => '09:00', 'subtitle' => 'Online']], 2, 1],
    'empty' => [[], 0, 0],
];
foreach ($listCases as $case => [$items, $columns, $subtitles]) {
    $listHtml = $twig->render('components/digest_list.html.twig', ['items' => $items]);
    $document = new DOMDocument();
    $document->loadHTML($listHtml);
    $xpath = new DOMXPath($document);
    foreach ($xpath->query('//tr') as $row) {
        if ($xpath->query('./td', $row)->length !== $columns) {
            $errors[] = "digest $case: inconsistent column count";
        }
    }
    if ($columns < 2 && str_contains($listHtml, 'email-list-time')) {
        $errors[] = "digest $case: unexpected time column";
    }
    if ($xpath->query('//div[@class="email-list-sub"]')->length !== $subtitles) {
        $errors[] = "digest $case: unexpected subtitle markup";
    }
    // Exercise the same optional fields through the complete HTML/text pair.
    $data = array_replace_recursive($fixture, $fixture['messages']['digest']);
    $data['digest']['items'] = $items;
    $twig->render('export.html.twig', $data + ['template' => 'messages/digest.html.twig']);
    $plain = $twig->render('messages/digest.txt.twig', $data);
    if (preg_match_all('/^- .+$/m', $plain) !== count($items) || !str_contains($plain, "\nOpen my calendar\n")) {
        $errors[] = "digest $case: plain-text list entries and action must occupy separate lines";
    }
    foreach ($items as $item) {
        if (!str_contains($plain, $item['title'])) {
            $errors[] = "digest $case: plain text lost a list item";
        }
    }
}

// Header: the text mark by default; an optional logo replaces only the mark.
// Both branches keep the brand name as text, so blocked images lose nothing.
$headerData = array_replace_recursive($fixture, $fixture['messages']['reset']);
unset($headerData['messages'], $headerData['brand']['logo']);
$logo = ['url' => 'https://example.org/email/logo.png', 'width' => 35, 'height' => 35];
$headerCases = [
    'text mark' => $headerData,
    'logo' => array_replace_recursive($headerData, ['brand' => ['logo' => $logo]]),
];
foreach ($headerCases as $case => $data) {
    $html = $twig->render('export.html.twig', $data + ['template' => 'messages/reset.html.twig']);
    $document = new DOMDocument();
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($document);
    $header = $xpath->query('//td[contains(concat(" ", @class, " "), " email-header ")]')->item(0);
    if ($header === null) {
        $errors[] = "header $case: missing .email-header";
        continue;
    }
    $brand = $xpath->query('.//td[contains(concat(" ", @class, " "), " email-brand ")]', $header)->item(0);
    if (trim($brand?->textContent ?? '') !== $fixture['brand']['name']) {
        $errors[] = "header $case: the brand name must stay as visible text";
    }
    $images = $xpath->query('.//img', $header);
    $marks = $xpath->query('.//td[contains(concat(" ", @class, " "), " email-logo-mark ")]', $header);
    if ($case === 'text mark') {
        if ($images->length !== 0 || $marks->length !== 1) {
            $errors[] = 'header text mark: expected the text mark and no image';
        }
        continue;
    }
    if ($images->length !== 1 || $marks->length !== 0) {
        $errors[] = 'header logo: expected one image replacing the text mark';
        continue;
    }
    $image = $images->item(0);
    if ($image->getAttribute('src') !== $logo['url']
        || $image->getAttribute('width') !== '35'
        || $image->getAttribute('height') !== '35'
        || !$image->hasAttribute('alt') || $image->getAttribute('alt') !== '') {
        $errors[] = 'header logo: image needs the given src, HTML width/height and an empty alt (the name is beside it)';
    }
    $cell = $image->parentNode;
    if (!$cell instanceof DOMElement || $cell->getAttribute('width') !== '35') {
        $errors[] = 'header logo: the image cell must carry the logo width for Outlook';
    }
}
// A logo without explicit dimensions must fail at render, not ship unsized.
try {
    $twig->render('export.html.twig', array_replace_recursive($headerData, ['brand' => ['logo' => ['url' => $logo['url']]]]) + ['template' => 'messages/reset.html.twig']);
    $errors[] = 'header logo: accepted a logo without width/height under strict_variables';
} catch (\Twig\Error\RuntimeError $error) {
    if (!str_contains($error->getMessage(), 'width')) {
        throw $error;
    }
}

if ($errors !== []) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}
printf("PASS: inliner fidelity, media queries, MSO width, Outlook-safe spacing, size, bgcolor/CSS agreement on %d message × theme renders; theme overrides; translated subcopy, optional digest fields and both header branches\n", count($renderCases));
