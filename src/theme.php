<?php

declare(strict_types=1);

/**
 * Resolves the `:root` custom properties of the email stylesheet.
 *
 * Email clients (and the CSS inliner) do not support var(), so tokens are
 * substituted at build time. Framework-neutral: the integrating app can call
 * this once and pass the result to Twig's inline_css filter.
 *
 * @return array{css: string, tokens: array<string, string>} CSS without the
 *         :root block and with every var() resolved; tokens keyed with
 *         underscores (--accent-ink => accent_ink) for use in Twig.
 */
function actual_emails_theme(string $css): array
{
    if (!preg_match('/:root\s*\{([^}]*)\}/', $css, $root)) {
        throw new RuntimeException('Theme: no :root token block found.');
    }
    preg_match_all('/--([a-z0-9-]+)\s*:\s*([^;]+);/i', $root[1], $pairs, PREG_SET_ORDER);
    $tokens = [];
    foreach ($pairs as [, $name, $value]) {
        $tokens[$name] = trim($value);
    }

    $resolved = str_replace($root[0], '', $css);
    $resolved = preg_replace_callback('/var\(\s*--([a-z0-9-]+)\s*\)/i', static function (array $m) use ($tokens): string {
        return $tokens[$m[1]] ?? throw new RuntimeException('Theme: unknown token --' . $m[1]);
    }, $resolved);

    // Keep the palette in one place: no literal colours outside :root.
    $withoutComments = preg_replace('~/\*.*?\*/~s', '', str_replace($root[0], '', $css));
    if (preg_match('/#[0-9a-f]{3,8}\b/i', $withoutComments, $hex)) {
        throw new RuntimeException('Theme: literal colour ' . $hex[0] . ' outside :root; add a token.');
    }

    $twigTokens = [];
    foreach ($tokens as $name => $value) {
        $twigTokens[str_replace('-', '_', $name)] = $value;
    }

    return ['css' => $resolved, 'tokens' => $twigTokens];
}
