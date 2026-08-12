<?php

declare(strict_types=1);

$root = dirname(__DIR__) . '/community/micro-contributions';
$required = [
    'glossary' => ['term', 'definition'],
    'flashcards' => ['question', 'answer', 'topic'],
    'use-cases' => ['subject', 'title', 'description'],
    'prompts' => ['name', 'prompt'],
    'tips' => ['title', 'tip', 'category'],
];

$errors = [];
foreach ($required as $folder => $keys) {
    $dir = $root . '/' . $folder;
    if (!is_dir($dir)) {
        $errors[] = "Missing directory: {$dir}";
        continue;
    }

    foreach (glob($dir . '/*.json') ?: [] as $file) {
        $raw = file_get_contents($file);
        if ($raw === false) {
            $errors[] = "Cannot read {$file}";
            continue;
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $errors[] = basename($file) . ': invalid JSON - ' . $e->getMessage();
            continue;
        }

        if (!is_array($data)) {
            $errors[] = basename($file) . ': root must be a JSON object';
            continue;
        }

        foreach ($keys as $key) {
            if (!array_key_exists($key, $data)) {
                $errors[] = basename($file) . ": missing key '{$key}'";
                continue;
            }
            if (!is_string($data[$key]) || trim($data[$key]) === '') {
                $errors[] = basename($file) . ": '{$key}' must be a non-empty string";
                continue;
            }
            if (stripos($data[$key], 'REPLACE_ME') !== false) {
                $errors[] = basename($file) . ': replace all REPLACE_ME placeholders';
            }
        }
    }
}

if ($errors) {
    fwrite(STDERR, "Micro-contribution validation failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, " - {$error}\n");
    }
    exit(1);
}

echo "Micro-contribution JSON validation passed.\n";