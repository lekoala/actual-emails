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
$theme = actual_emails_theme($css);

$twig = new Environment(new FilesystemLoader($root . '/src/templates'), [
    'strict_variables' => true,
    'autoescape' => 'html',
    'cache' => false,
]);
$twig->addExtension(new CssInlinerExtension());
$twig->addGlobal('theme', $theme['tokens']);
$twig->addGlobal('email_css', $theme['css']);

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

$probeTwig = new Environment(new ArrayLoader(['probe' => $probe]), [
    'strict_variables' => true,
    'autoescape' => 'html',
    'cache' => false,
]);
$probeTwig->addExtension(new CssInlinerExtension());
$probeHtml = $probeTwig->render('probe');

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
// 2. Media queries and 3. Outlook bgcolor agreement, per rendered message.
// ---------------------------------------------------------------------------
foreach ($fixture['messages'] as $name => $message) {
    $data = array_merge($fixture, $message);
    unset($data['messages']);
    $html = $twig->render('export.html.twig', $data + ['template' => 'messages/' . $name . '.html.twig']);

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

if ($errors !== []) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}
printf("PASS: inliner fidelity, media queries and bgcolor/CSS agreement on %d messages\n", count($fixture['messages']));
