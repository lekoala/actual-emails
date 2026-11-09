<?php

declare(strict_types=1);

// WCAG 2.x contrast for the text/background token pairs the layout actually
// uses, in the default palette and every theme. Not an accessibility engine:
// a regression guard for whoever edits a palette.
require dirname(__DIR__) . '/src/theme.php';

$root = dirname(__DIR__);
$css = (string) file_get_contents($root . '/src/templates/styles/email.css');

// AA for normal text. Buttons are 16px bold, below the 18.66px bold "large
// text" threshold, so they get no relaxation either.
const MIN_RATIO = 4.5;

// [text token, background token, where it appears]
$pairs = [
    ['on_accent', 'accent', 'primary button, logo mark'],
    ['accent', 'surface', 'links'],
    ['accent_ink', 'surface', 'secondary button'],
    ['ink', 'surface', 'titles, values'],
    ['text', 'surface', 'body copy'],
    ['muted', 'surface', 'labels, help, card foot'],
    ['muted', 'page', 'footer'],
    ['accent_ink', 'surface_tint', 'notice'],
    ['warning_ink', 'warning_bg', 'warning notice'],
    ['dark_on_button', 'dark_button', 'dark: primary button, logo mark'],
    ['dark_ink', 'dark_surface', 'dark: titles, values'],
    ['dark_muted', 'dark_surface', 'dark: body copy, labels'],
    ['dark_muted', 'dark_page', 'dark: footer'],
    ['dark_accent', 'dark_surface', 'dark: links, secondary button'],
    ['dark_accent', 'dark_tint', 'dark: notice'],
    ['dark_warning_ink', 'dark_warning_bg', 'dark: warning notice'],
];

function luminance(string $hex): float
{
    if (!preg_match('/^#([0-9a-f]{6})$/i', $hex, $m)) {
        throw new RuntimeException("Contrast: expected a 6-digit hex colour, got '$hex'");
    }
    $channels = array_map(static function (string $pair): float {
        $c = hexdec($pair) / 255;
        return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    }, str_split($m[1], 2));
    return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
}

function contrast(string $a, string $b): float
{
    [$hi, $lo] = [max(luminance($a), luminance($b)), min(luminance($a), luminance($b))];
    return ($hi + 0.05) / ($lo + 0.05);
}

$themes = ['default' => null];
foreach (glob($root . '/src/templates/styles/themes/*.css') ?: [] as $path) {
    $themes[basename($path, '.css')] = (string) file_get_contents($path);
}

$errors = [];
foreach ($themes as $name => $overrides) {
    $tokens = actual_emails_theme($css, $overrides)['tokens'];
    foreach ($pairs as [$fg, $bg, $where]) {
        $ratio = contrast($tokens[$fg], $tokens[$bg]);
        if ($ratio < MIN_RATIO) {
            $errors[] = sprintf('%s: %s on %s is %.2f:1 (< %.1f), used for %s', $name, $fg, $bg, $ratio, MIN_RATIO, $where);
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}
printf("PASS: WCAG AA contrast (%d token pairs × %d palettes)\n", count($pairs), count($themes));
