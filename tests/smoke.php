<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$fixture = json_decode((string) file_get_contents($root . '/src/fixtures/demo.json'), true, 512, JSON_THROW_ON_ERROR);
$errors = [];

// Every class used by the rendered emails must exist in the source stylesheet.
// The check runs on dist/ rather than on Twig sources because classes built
// from variables (spacer sizes, list separators) are only resolved at render.
$css = (string) file_get_contents($root . '/src/templates/styles/email.css');
preg_match_all('/\.([a-z][a-z0-9_-]*)/i', $css, $classMatch);
$knownClasses = array_fill_keys(array_map('strtolower', $classMatch[1]), true);

$htmlLinks = static function (string $html): array {
    preg_match_all('/href="([^"]+)"/', $html, $matches);
    // Attributes are HTML-escaped (`&amp;`); compare the URL the client will actually open.
    $urls = array_map(static fn (string $url): string => html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $matches[1]);
    return array_values(array_unique(array_filter($urls, static fn (string $url): bool => str_starts_with($url, 'http'))));
};
$textLinks = static function (string $text): array {
    preg_match_all('~https?://[^\s<>"]+~', $text, $matches);
    return array_values(array_unique($matches[0]));
};

foreach (array_keys($fixture['messages']) as $name) {
    $htmlFile = "$root/dist/$name.html";
    $textFile = "$root/dist/$name.txt";
    if (!is_file($htmlFile) || !is_file($textFile)) {
        $errors[] = "$name missing output";
        continue;
    }
    $html = (string) file_get_contents($htmlFile);
    $plain = trim((string) file_get_contents($textFile));
    foreach (['<!doctype html>', 'lang="en"', 'role="presentation"', 'class="email-preheader"', 'style=', '<title>', '<h1', '<!--[if mso]>'] as $expected) {
        if (stripos($html, $expected) === false) {
            $errors[] = "$name HTML missing $expected";
        }
    }
    // Media queries and client prefixes must survive inlining.
    foreach (['@media screen and (max-width: 620px)', '@media (prefers-color-scheme: dark)', 'name="format-detection"'] as $expected) {
        if (!str_contains($html, $expected)) {
            $errors[] = "$name HTML missing $expected";
        }
    }
    if (str_contains($html, 'var(--')) {
        $errors[] = "$name HTML still contains an unresolved var() token";
    }
    if (preg_match('/<script\b|<iframe\b|<img\b[^>]+src=["\']https?:/i', $html)) {
        $errors[] = "$name includes a forbidden script/iframe/remote image";
    }
    // A secondary-style primary CTA is a real regression (caller context leak).
    if (substr_count($html, 'class="email-button-ghost-td"') > substr_count($html, 'class="email-button-td"')) {
        $errors[] = "$name renders more secondary buttons than primary ones";
    }
    if (preg_match('/\{\{|\{%/', $html . $plain)) {
        $errors[] = "$name includes an unrendered template expression";
    }
    if (strlen($plain) < 90 || preg_match('/<\/?[a-z][\s>]/i', $plain)) {
        $errors[] = "$name plain text appears missing or invalid";
    }
    // Plain text must never be HTML-escaped (e.g. `&amp;` in a link).
    if (preg_match('/&(?:amp|lt|gt|quot|#0?39|#x27);/', $plain)) {
        $errors[] = "$name plain text contains HTML entities";
    }
    if (!str_contains($plain, 'https://example.org/')) {
        $errors[] = "$name plain text lacks a usable link";
    }

    preg_match_all('/class="([^"]*)"/', $html, $classAttributes);
    $used = preg_split('/\s+/', implode(' ', $classAttributes[1]), -1, PREG_SPLIT_NO_EMPTY);
    foreach (array_unique($used) as $class) {
        if (!isset($knownClasses[strtolower($class)])) {
            $errors[] = "$name uses undefined class $class";
        }
    }

    // A missing footer or fallback link in one version is a real defect.
    $fromHtml = $htmlLinks($html);
    $fromText = $textLinks($plain);
    sort($fromHtml);
    sort($fromText);
    if ($fromHtml !== $fromText) {
        $errors[] = "$name link mismatch: HTML [" . implode(', ', $fromHtml) . '] vs text [' . implode(', ', $fromText) . ']';
    }
}

if ($errors !== []) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}
printf("PASS: %d HTML + plain-text message pairs\n", count($fixture['messages']));
