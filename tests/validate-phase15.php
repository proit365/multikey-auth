<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$required = [
    'llms.txt',
    'SEO_METADATA.md',
    'website/product-landing.md',
    'website/documentation-page.md',
    'website/internal-linking.md',
    'website/structured-data.md',
];
$failures = [];
foreach ($required as $file) {
    $path = $root . '/' . $file;
    if (!is_file($path) || filesize($path) === 0) {
        $failures[] = 'Missing or empty file: ' . $file;
    }
}

$landing = file_get_contents($root . '/website/product-landing.md');
$llms = file_get_contents($root . '/llms.txt');
$seo = file_get_contents($root . '/SEO_METADATA.md');
$combined = $landing . "\n" . $llms . "\n" . $seo;

if (!str_contains($combined, 'https://github.com/proit365/multikey-auth')) {
    $failures[] = 'Verified repository URL is missing.';
}
if (str_contains($combined, 'https://proit365.com/products/multikey-auth')
    || str_contains($combined, 'https://proit365.com/products/multikey-auth/docs')) {
    $failures[] = 'A suggested unpublished product/documentation URL was included.';
}

foreach ([
    'independent security review',
    'not production-ready',
    'live',
] as $requiredPhrase) {
    if (!str_contains(strtolower($combined), strtolower($requiredPhrase))) {
        $failures[] = 'Expected status phrase is missing: ' . $requiredPhrase;
    }
}

$documentationLinks = [
    $root . '/docs/index.md',
    $root . '/specification/mkap-v1.md',
    $root . '/specification/test-vectors-v1.json',
    $root . '/docs/security.md',
    $root . '/docs/troubleshooting.md',
];
foreach ($documentationLinks as $path) {
    if (!is_file($path)) {
        $failures[] = 'Missing documentation target: ' . basename($path);
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, 'FAIL ' . $failure . PHP_EOL);
    }
    exit(1);
}

fwrite(STDOUT, "Phase 15 website/SEO validation passed.\n");
