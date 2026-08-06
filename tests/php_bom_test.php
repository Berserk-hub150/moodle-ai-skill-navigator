<?php

$root = realpath(__DIR__ . '/../plugins');
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
$failed = [];

foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }

    $handle = fopen($file->getPathname(), 'rb');
    $prefix = $handle ? fread($handle, 3) : '';

    if ($handle) {
        fclose($handle);
    }

    if ($prefix === "\xEF\xBB\xBF") {
        $failed[] = substr($file->getPathname(), strlen(dirname($root)) + 1);
    }
}

if ($failed) {
    fwrite(STDERR, "UTF-8 BOM found in PHP files:\n- " . implode("\n- ", $failed) . "\n");
    exit(1);
}

echo "php_bom_test: OK\n";
