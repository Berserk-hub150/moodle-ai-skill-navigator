<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the.
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License.
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * AI Skill Navigator plugin file.
 *
 * @package    local_aiskillnavigator
 * @copyright  2026 Luca Magrini
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

/**
 * Local aiskillnavigator flashcard deck limits helper.
 */
function local_aiskillnavigator_flashcard_limits(): array {
    return [
        'title' => 120,
        'description' => 500,
        'question' => 500,
        'answer' => 1500,
    ];
}

/**
 * Local aiskillnavigator flashcard failure helper.
 *
 * @param string $code Error code resolved against the flashcards_error_* strings.
 * @param string $context Field or file name shown with the message.
 */
function local_aiskillnavigator_flashcard_error(string $code, string $context = ''): array {
    return ['deck' => null, 'errorcode' => $code, 'errorcontext' => $context];
}

/**
 * Local aiskillnavigator flashcard text field helper.
 *
 * @param mixed $value Raw decoded value.
 * @param string $field Field name used in the error context.
 * @param int $maxlength Maximum number of characters allowed.
 * @param string|null $error Receives the error code when the value is rejected.
 */
function local_aiskillnavigator_flashcard_text(
    $value,
    string $field,
    int $maxlength,
    ?string &$error = null
): string {
    if (!is_string($value)) {
        $error = 'fieldtype';
        return '';
    }

    $text = trim($value);

    if ($text === '') {
        $error = 'fieldempty';
        return '';
    }

    if (local_aiskillnavigator_flashcard_length($text) > $maxlength) {
        $error = 'fieldtoolong';
        return '';
    }

    $error = null;
    return $text;
}

/**
 * Local aiskillnavigator flashcard length helper.
 *
 * @param string $text Text to measure in characters rather than bytes.
 */
function local_aiskillnavigator_flashcard_length(string $text): int {
    return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
}

/**
 * Local aiskillnavigator flashcard card helper.
 *
 * @param mixed $card Raw decoded card.
 * @param int $index Zero based position used in the error context.
 */
function local_aiskillnavigator_flashcard_card($card, int $index): array {
    $limits = local_aiskillnavigator_flashcard_limits();
    $position = 'cards[' . $index . ']';

    if (!is_array($card) || array_is_list($card)) {
        return local_aiskillnavigator_flashcard_error('cardtype', $position);
    }

    foreach (array_keys($card) as $key) {
        if ($key !== 'question' && $key !== 'answer') {
            return local_aiskillnavigator_flashcard_error('unknownfield', $position . '.' . $key);
        }
    }

    $values = [];

    foreach (['question', 'answer'] as $field) {
        if (!array_key_exists($field, $card)) {
            return local_aiskillnavigator_flashcard_error('fieldmissing', $position . '.' . $field);
        }

        $values[$field] = local_aiskillnavigator_flashcard_text(
            $card[$field],
            $field,
            $limits[$field],
            $error
        );

        if ($error !== null) {
            return local_aiskillnavigator_flashcard_error($error, $position . '.' . $field);
        }
    }

    return ['deck' => $values, 'errorcode' => null, 'errorcontext' => ''];
}

/**
 * Local aiskillnavigator flashcard deck parser.
 *
 * @param string $json Raw deck contents.
 */
function local_aiskillnavigator_flashcard_parse_deck(string $json): array {
    $limits = local_aiskillnavigator_flashcard_limits();
    $decoded = json_decode($json, true);

    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
        return local_aiskillnavigator_flashcard_error('invalidjson', json_last_error_msg());
    }

    if (!is_array($decoded) || array_is_list($decoded)) {
        return local_aiskillnavigator_flashcard_error('decktype');
    }

    foreach (array_keys($decoded) as $key) {
        if ($key !== 'title' && $key !== 'description' && $key !== 'cards') {
            return local_aiskillnavigator_flashcard_error('unknownfield', (string)$key);
        }
    }

    if (!array_key_exists('title', $decoded)) {
        return local_aiskillnavigator_flashcard_error('fieldmissing', 'title');
    }

    $title = local_aiskillnavigator_flashcard_text($decoded['title'], 'title', $limits['title'], $error);

    if ($error !== null) {
        return local_aiskillnavigator_flashcard_error($error, 'title');
    }

    $description = '';

    if (array_key_exists('description', $decoded)) {
        if (!is_string($decoded['description'])) {
            return local_aiskillnavigator_flashcard_error('fieldtype', 'description');
        }

        $description = trim($decoded['description']);

        if (local_aiskillnavigator_flashcard_length($description) > $limits['description']) {
            return local_aiskillnavigator_flashcard_error('fieldtoolong', 'description');
        }
    }

    if (!array_key_exists('cards', $decoded)) {
        return local_aiskillnavigator_flashcard_error('fieldmissing', 'cards');
    }

    if (!is_array($decoded['cards']) || !array_is_list($decoded['cards'])) {
        return local_aiskillnavigator_flashcard_error('cardstype');
    }

    if ($decoded['cards'] === []) {
        return local_aiskillnavigator_flashcard_error('nocards');
    }

    $cards = [];

    foreach ($decoded['cards'] as $index => $card) {
        $parsed = local_aiskillnavigator_flashcard_card($card, (int)$index);

        if ($parsed['errorcode'] !== null) {
            return $parsed;
        }

        $cards[] = $parsed['deck'];
    }

    return [
        'deck' => ['title' => $title, 'description' => $description, 'cards' => $cards],
        'errorcode' => null,
        'errorcontext' => '',
    ];
}

/**
 * Local aiskillnavigator flashcard deck file loader.
 *
 * @param string $path Absolute path to a deck file.
 */
function local_aiskillnavigator_flashcard_load_deck_file(string $path): array {
    $name = basename($path);

    if (!is_file($path) || !is_readable($path)) {
        return local_aiskillnavigator_flashcard_error('deckunreadable', $name);
    }

    $contents = file_get_contents($path);

    if ($contents === false) {
        return local_aiskillnavigator_flashcard_error('deckunreadable', $name);
    }

    $result = local_aiskillnavigator_flashcard_parse_deck($contents);

    if ($result['errorcode'] !== null && $result['errorcontext'] === '') {
        $result['errorcontext'] = $name;
    }

    return $result;
}

/**
 * Local aiskillnavigator flashcard deck directory helper.
 */
function local_aiskillnavigator_flashcard_deck_directory(): string {
    return realpath(__DIR__ . '/../flashcards/decks') ?: __DIR__ . '/../flashcards/decks';
}

/**
 * Local aiskillnavigator flashcard deck file list helper.
 *
 * @param string|null $directory Directory to read, defaulting to the shipped decks.
 */
function local_aiskillnavigator_flashcard_list_deck_files(?string $directory = null): array {
    $directory = $directory ?? local_aiskillnavigator_flashcard_deck_directory();
    $files = glob(rtrim($directory, '/') . '/*.json');

    if ($files === false) {
        return [];
    }

    sort($files, SORT_STRING);

    return $files;
}
