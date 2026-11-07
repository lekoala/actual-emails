<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/src/theme.php';

use Twig\Environment;
use Twig\Extra\CssInliner\CssInlinerExtension;
use Twig\Loader\FilesystemLoader;

$root = dirname(__DIR__);
// --check: render in memory and fail if the committed dist/ is stale.
$check = in_array('--check', $argv, true);
$stale = [];
$fixture = json_decode((string) file_get_contents($root . '/src/fixtures/demo.json'), true, 512, JSON_THROW_ON_ERROR);
$twig = new Environment(new FilesystemLoader($root . '/src/templates'), [
    'strict_variables' => true,
    'autoescape' => 'html',
    'cache' => false,
]);
$twig->addExtension(new CssInlinerExtension());

// Resolve theme tokens once: the inliner gets plain CSS, templates get `theme.*`.
$theme = actual_emails_theme((string) file_get_contents($root . '/src/templates/styles/email.css'));
$twig->addGlobal('theme', $theme['tokens']);
$twig->addGlobal('email_css', $theme['css']);

foreach ($fixture['messages'] as $name => $message) {
    if (!preg_match('/^[a-z0-9_-]+$/', $name)) {
        throw new RuntimeException('Unsafe fixture name: ' . $name);
    }
    $data = array_merge($fixture, $message);
    unset($data['messages']);

    // Applying inline_css to the entire document preserves the layout's table structure.
    $html = $twig->render('export.html.twig', $data + ['template' => 'messages/' . $name . '.html.twig']);
    $txt = $twig->render('messages/' . $name . '.txt.twig', $data);
    $outputs = [$name . '.html' => $html, $name . '.txt' => trim($txt) . "\n"];
    foreach ($outputs as $file => $content) {
        $path = $root . '/dist/' . $file;
        if ($check) {
            if (!is_file($path) || file_get_contents($path) !== $content) {
                $stale[] = $file;
            }
        } else {
            file_put_contents($path, $content);
        }
    }
    if (!$check) {
        printf("Built: %s.html + %s.txt\n", $name, $name);
    }
}

if ($check) {
    if ($stale !== []) {
        fwrite(STDERR, 'dist/ is stale, run `composer build`: ' . implode(', ', $stale) . "\n");
        exit(1);
    }
    echo "dist/ is up to date\n";
}
