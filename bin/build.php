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
$expectedFiles = [];
$fixture = json_decode((string) file_get_contents($root . '/src/fixtures/demo.json'), true, 512, JSON_THROW_ON_ERROR);
$stylesheet = (string) file_get_contents($root . '/src/templates/styles/email.css');

// Each theme in styles/themes/ only overrides tokens. The default theme exports
// `<message>.html` + `.txt`; a theme exports `<message>--<theme>.html` (the text
// version does not depend on the theme). Exports stay flat in dist/ so relative
// demo assets resolve the same way for every theme.
$themes = ['' => null];
foreach (glob($root . '/src/templates/styles/themes/*.css') ?: [] as $themePath) {
    $themeName = basename($themePath, '.css');
    if (!preg_match('/^[a-z0-9]+$/', $themeName)) {
        throw new RuntimeException('Unsafe theme name: ' . $themeName);
    }
    $themes[$themeName] = (string) file_get_contents($themePath);
}

$outputs = [];
foreach ($themes as $themeName => $overrides) {
    $twig = new Environment(new FilesystemLoader($root . '/src/templates'), [
        'strict_variables' => true,
        // By file name: *.html.twig is HTML-escaped; *.txt.twig is plain text, where
        // HTML entities would corrupt URLs (`&amp;`) and names (`&#039;`).
        'autoescape' => 'name',
        'cache' => false,
    ]);
    $twig->addExtension(new CssInlinerExtension());
    // Resolve theme tokens once: the inliner gets plain CSS, templates get `theme.*`.
    $theme = actual_emails_theme($stylesheet, $overrides);
    $twig->addGlobal('theme', $theme['tokens']);
    $twig->addGlobal('email_css', $theme['css']);

    foreach ($fixture['messages'] as $name => $message) {
        // No `--` in names: it separates the message from its theme in dist/.
        if (!preg_match('/^[a-z0-9]+(?:[_-][a-z0-9]+)*$/', $name)) {
            throw new RuntimeException('Unsafe fixture name: ' . $name);
        }
        // Recursive so a message can add one brand option (e.g. `brand.logo`) without repeating the brand.
        $data = array_replace_recursive($fixture, $message);
        unset($data['messages']);

        // Applying inline_css to the entire document preserves the layout's table structure.
        $html = $twig->render('export.html.twig', $data + ['template' => 'messages/' . $name . '.html.twig']);
        if ($themeName === '') {
            $outputs[$name . '.html'] = $html;
            $outputs[$name . '.txt'] = trim($twig->render('messages/' . $name . '.txt.twig', $data)) . "\n";
        } else {
            $outputs[$name . '--' . $themeName . '.html'] = $html;
        }
    }
}

foreach ($outputs as $file => $content) {
    $expectedFiles[$file] = true;
    $path = $root . '/dist/' . $file;
    if ($check) {
        if (!is_file($path) || file_get_contents($path) !== $content) {
            $stale[] = $file;
        }
    } elseif (file_put_contents($path, $content) === false) {
        throw new RuntimeException('Could not write export: ' . $file);
    }
}
if (!$check) {
    printf("Built: %d messages (HTML + text) in %d themes: %s\n", count($fixture['messages']), count($themes), implode(', ', array_map(static fn ($t) => $t === '' ? 'default' : $t, array_keys($themes))));
}

// Demo images are copied next to the exports so the gallery stays offline.
// The fixture references them relatively; a sending host uses absolute HTTPS URLs.
foreach (glob($root . '/src/assets/*.png') ?: [] as $source) {
    $file = 'assets/' . basename($source);
    $path = $root . '/dist/' . $file;
    $content = (string) file_get_contents($source);
    if ($check) {
        if (!is_file($path) || file_get_contents($path) !== $content) {
            $stale[] = $file;
        }
    } elseif ((!is_dir(dirname($path)) && !mkdir(dirname($path))) || file_put_contents($path, $content) === false) {
        throw new RuntimeException('Could not copy asset: ' . $file);
    } else {
        printf("Copied: %s\n", $file);
    }
}

// Only generated HTML/text exports are managed here; leave other files alone.
// --check must report obsolete exports without deleting them.
foreach (['html', 'txt'] as $extension) {
    foreach (glob($root . '/dist/*.' . $extension) ?: [] as $path) {
        $file = basename($path);
        if (!is_file($path) || isset($expectedFiles[$file])) {
            continue;
        }
        if ($check) {
            $stale[] = $file . ' (obsolete)';
        } elseif (!unlink($path)) {
            throw new RuntimeException('Could not remove obsolete export: ' . $file);
        } else {
            printf("Removed obsolete export: %s\n", $file);
        }
    }
}

if ($check) {
    if ($stale !== []) {
        fwrite(STDERR, 'dist/ is stale, run `composer build`: ' . implode(', ', $stale) . "\n");
        exit(1);
    }
    echo "dist/ is up to date\n";
}
