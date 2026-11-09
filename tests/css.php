<?php

declare(strict_types=1);

// Static rules for the single source stylesheet. An optional path lets the
// rules be exercised against a failing fixture; it defaults to email.css.
$root = dirname(__DIR__);
// Without an argument, lint email.css and every theme, one process per file.
if (!isset($argv[1])) {
    $status = 0;
    foreach (array_merge([$root . '/src/templates/styles/email.css'], glob($root . '/src/templates/styles/themes/*.css') ?: []) as $file) {
        passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' ' . escapeshellarg($file), $code);
        $status = max($status, $code);
    }
    exit($status);
}
$path = $argv[1];
$errors = [];

if (!is_file($path)) {
    fwrite(STDERR, "Stylesheet not found: $path\n");
    exit(1);
}
$css = (string) file_get_contents($path);
$source = (string) preg_replace('~/\*.*?\*/~s', '', $css);
// @import/@charset are statements without a block; drop them before parsing
// rules so the following selector is not read as part of their text.
$rules = (string) preg_replace('/@[a-z-]+[^{;}]*;/i', '', $source);

// !important belongs in the layout's media queries, not in inlined CSS: an
// inlined !important would beat the mobile stacking rules.
if (str_contains($source, '!important')) {
    $errors[] = 'email.css must not rely on !important; keep it in the layout media queries';
}

// Remote resources and embedded fonts are outside the delivery contract.
if (preg_match('/@import|@font-face|url\s*\(/i', $source, $forbidden)) {
    $errors[] = 'email.css must not use ' . $forbidden[0] . '; no remote assets or external fonts in email';
}

// Six-digit lowercase hex only: short hex fails in Outlook.com and Lotus Notes.
preg_match_all('/#([0-9a-fA-F]{3,8})\b/', $source, $hexes);
foreach (array_unique($hexes[1]) as $hex) {
    if (strlen($hex) !== 6) {
        $errors[] = "#$hex must be a 6-digit hex colour";
    } elseif ($hex !== strtolower($hex)) {
        $errors[] = "#$hex must be lowercase";
    }
}

// Colour keywords bypass the theme tokens.
$keywords = 'white|black|silver|gray|grey|maroon|red|purple|fuchsia|green|lime|olive|yellow|navy|blue|teal|aqua|orange';

preg_match_all('/([^{}]+)\{([^{}]*)\}/s', $rules, $matches, PREG_SET_ORDER);
foreach ($matches as $match) {
    $selector = trim((string) preg_replace('/\s+/', ' ', $match[1]));
    $body = trim($match[2]);
    if ($body === '') {
        $errors[] = "empty ruleset: $selector";
        continue;
    }

    // #ids in selectors defeat the class-based contract and are fragile in clients.
    if (preg_match('/#[a-z][a-z0-9_-]*/i', $selector, $id)) {
        $errors[] = "id selector $id[0] in $selector; use a class";
    }
    preg_match_all('/\.([A-Za-z][A-Za-z0-9_-]*)/', $selector, $classes);
    foreach (array_unique($classes[1]) as $class) {
        if (!str_starts_with($class, 'email-')) {
            $errors[] = "class .$class in $selector does not use the email- prefix";
        }
    }

    $seen = [];
    foreach (explode(';', $body) as $declaration) {
        $declaration = trim($declaration);
        if ($declaration === '') {
            continue;
        }
        if (!preg_match('/^([a-z-]+)\s*:\s*(.+)$/is', $declaration, $parsed)) {
            $errors[] = "unparseable declaration '$declaration' in $selector";
            continue;
        }
        // A duplicated property is almost always a typo that silently overrides.
        $property = strtolower($parsed[1]);
        if (isset($seen[$property])) {
            $errors[] = "duplicate property '$property' in $selector";
        }
        $seen[$property] = true;
        // Outlook Word rounds unitless line heights and leaves 1px seams
        // (Outlook 2019, November 2026): whole pixels only.
        if ($property === 'line-height' && !preg_match('/^(0|\d+px)$/', trim($parsed[2]))) {
            $errors[] = "line-height '" . trim($parsed[2]) . "' in $selector; use whole pixels";
        }
        if (preg_match('/\b(' . $keywords . ')\b/i', $parsed[2], $keyword)) {
            $errors[] = "colour keyword '$keyword[1]' in $selector; use a theme token";
        }
    }
}

if ($matches === []) {
    $errors[] = 'no rules parsed; is this a stylesheet?';
}

if ($errors !== []) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}
printf("PASS: stylesheet lint (%d rules in %s)\n", count($matches), basename($path));
