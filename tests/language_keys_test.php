<?php
/** Reject duplicate language keys, which PHP would silently overwrite. */
$files = glob(__DIR__ . '/../plugins/*/lang/*/*.php');
foreach ($files as $file) {
    preg_match_all('/^\s*\$string\[[\'\"]([^\'\"]+)[\'\"]\]\s*=/m', file_get_contents($file), $matches);
    foreach (array_count_values($matches[1]) as $key => $count) {
        if ($count > 1) { throw new RuntimeException(basename(dirname($file)) . ': duplicate language key ' . $key); }
    }
}
echo "language_keys_test: OK\n";
