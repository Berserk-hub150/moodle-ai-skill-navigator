<?php

define('MOODLE_INTERNAL', true);
require_once(__DIR__ . '/../plugins/aiskillnavigator/includes/flashcard_deck_helper.php');

function check($expected, $actual, $message) {
    if ($expected !== $actual) { throw new RuntimeException($message . ': ' . var_export($actual, true)); }
}
function parse($json) { return local_aiskillnavigator_flashcard_parse_deck($json); }
function rejects($json, $code, $message) { check($code, parse($json)['errorcode'], $message); }
function deck($cards, $extra = '') {
    return '{"title":"Deck"' . $extra . ',"cards":[' . $cards . ']}';
}
$card = '{"question":"Q","answer":"A"}';

$valid = parse('{"title":"  Spaced deck  ","description":"  Notes  ","cards":[' . $card . ',{"question":"Q2","answer":"A2"}]}');
check(null, $valid['errorcode'], 'A schema-conforming deck is accepted');
check('Spaced deck', $valid['deck']['title'], 'Surrounding whitespace is removed from the title');
check('Notes', $valid['deck']['description'], 'Surrounding whitespace is removed from the description');
check(2, count($valid['deck']['cards']), 'Every card is kept');
check(['question' => 'Q2', 'answer' => 'A2'], $valid['deck']['cards'][1], 'Cards keep their order and fields');

$minimal = parse(deck($card));
check(null, $minimal['errorcode'], 'A one-card deck is accepted');
check('', $minimal['deck']['description'], 'A missing description becomes an empty string');
check(1, count($minimal['deck']['cards']), 'A one-card deck keeps its single card');

rejects('{"title":"Deck",}', 'invalidjson', 'Malformed JSON is rejected');
rejects('', 'invalidjson', 'Empty input is rejected');
rejects('[]', 'decktype', 'A JSON array is not a deck');
rejects('"deck"', 'decktype', 'A JSON string is not a deck');

rejects('{"cards":[' . $card . ']}', 'fieldmissing', 'A deck without a title is rejected');
rejects('{"title":"Deck"}', 'fieldmissing', 'A deck without cards is rejected');
rejects('{"title":"","cards":[' . $card . ']}', 'fieldempty', 'An empty title is rejected');
rejects('{"title":"   ","cards":[' . $card . ']}', 'fieldempty', 'A whitespace-only title is rejected');
rejects('{"title":42,"cards":[' . $card . ']}', 'fieldtype', 'A non-string title is rejected');
rejects('{"title":"' . str_repeat('a', 121) . '","cards":[' . $card . ']}', 'fieldtoolong', 'An over-long title is rejected');
rejects('{"title":"Deck","description":"' . str_repeat('a', 501) . '","cards":[' . $card . ']}', 'fieldtoolong',
    'An over-long description is rejected');
rejects('{"title":"Deck","description":7,"cards":[' . $card . ']}', 'fieldtype', 'A non-string description is rejected');

rejects('{"title":"Deck","cards":[]}', 'nocards', 'A deck without cards is rejected');
rejects('{"title":"Deck","cards":{"question":"Q","answer":"A"}}', 'cardstype', 'A card object outside an array is rejected');
rejects('{"title":"Deck","cards":"none"}', 'cardstype', 'A non-array card list is rejected');

rejects(deck('"card"'), 'cardtype', 'A non-object card is rejected');
rejects(deck('{"answer":"A"}'), 'fieldmissing', 'A card without a question is rejected');
rejects(deck('{"question":"Q"}'), 'fieldmissing', 'A card without an answer is rejected');
rejects(deck('{"question":"Q","answer":"  "}'), 'fieldempty', 'A whitespace-only answer is rejected');
rejects(deck('{"question":"Q","answer":[]}'), 'fieldtype', 'A non-string answer is rejected');
rejects(deck('{"question":"' . str_repeat('a', 501) . '","answer":"A"}'), 'fieldtoolong', 'An over-long question is rejected');
rejects(deck('{"question":"Q","answer":"' . str_repeat('a', 1501) . '"}'), 'fieldtoolong', 'An over-long answer is rejected');

rejects('{"title":"Deck","difficulty":"beginner","cards":[' . $card . ']}', 'unknownfield', 'An unknown deck field is rejected');
rejects(deck('{"question":"Q","answer":"A","hint":"H"}'), 'unknownfield', 'An unknown card field is rejected');
check('difficulty', parse('{"title":"Deck","difficulty":1,"cards":[' . $card . ']}')['errorcontext'],
    'The rejected deck field is named');
check('cards[0].hint', parse(deck('{"question":"Q","answer":"A","hint":"H"}'))['errorcontext'], 'The rejected card field is named');
check('cards[1].answer', parse(deck($card . ',{"question":"Q","answer":""}'))['errorcontext'], 'The failing card is identified');

check(null, parse('{"title":"' . str_repeat('á', 120) . '","cards":[' . $card . ']}')['errorcode'],
    'A title of 120 accented characters is accepted');
rejects('{"title":"' . str_repeat('á', 121) . '","cards":[' . $card . ']}', 'fieldtoolong',
    'An accented title over the limit is rejected');

$tempdir = sys_get_temp_dir() . '/aisn_flashcards_' . getmypid();
@mkdir($tempdir);
file_put_contents($tempdir . '/good.json', deck($card));
file_put_contents($tempdir . '/broken.json', '{"title":');
check(null, local_aiskillnavigator_flashcard_load_deck_file($tempdir . '/good.json')['errorcode'], 'A valid deck file is read');
$broken = local_aiskillnavigator_flashcard_load_deck_file($tempdir . '/broken.json');
check('invalidjson', $broken['errorcode'], 'A malformed deck file is rejected');
$missing = local_aiskillnavigator_flashcard_load_deck_file($tempdir . '/absent.json');
check('deckunreadable', $missing['errorcode'], 'A missing deck file is reported');
check('absent.json', $missing['errorcontext'], 'The unreadable deck file is named');
check([$tempdir . '/broken.json', $tempdir . '/good.json'], local_aiskillnavigator_flashcard_list_deck_files($tempdir),
    'Deck files are listed in a stable order');
check([], local_aiskillnavigator_flashcard_list_deck_files($tempdir . '/absent'), 'A missing deck directory lists nothing');
array_map('unlink', glob($tempdir . '/*.json'));
rmdir($tempdir);

foreach (glob(__DIR__ . '/../community/flashcards/decks/*.json') as $path) {
    check(null, local_aiskillnavigator_flashcard_load_deck_file($path)['errorcode'], 'Community deck ' . basename($path));
}

$shipped = local_aiskillnavigator_flashcard_list_deck_files();
check(true, $shipped !== [], 'The plugin ships a deck');
foreach ($shipped as $path) {
    check(null, local_aiskillnavigator_flashcard_load_deck_file($path)['errorcode'], 'Shipped deck ' . basename($path));
}

echo "flashcard_deck_test: OK\n";
