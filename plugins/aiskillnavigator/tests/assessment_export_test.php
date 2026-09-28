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

namespace local_aiskillnavigator;

use local_aiskillnavigator\service\assessment_export;

/**
 * Verify exports against Moodle's importer and spreadsheet formula boundaries.
 *
 * @covers \local_aiskillnavigator\service\assessment_export
 */
final class assessment_export_test extends \advanced_testcase {
    /**
     * Mathematical symbols and literal markup survive Moodle's actual GIFT parser.
     */
    public function test_gift_round_trip_preserves_text_and_answer_key(): void {
        global $CFG;
        require_once($CFG->dirroot . '/question/format.php');
        require_once($CFG->dirroot . '/question/format/gift/format.php');
        $question = [
            'question' => "Solve {x}: x = 2 ~ 3 # note \\ path\nUse <b>literal</b> text.",
            'options' => ['{a=b}', '%100% is text', 'C:\\data # : ~', '[html]<script>literal</script>'],
            'correct_index' => 2,
        ];
        $parsed = (new \qformat_gift())->readquestion(explode("\n", assessment_export::gift([$question])));
        $this->assertIsObject($parsed);
        $this->assertSame('multichoice', $parsed->qtype);
        $this->assertSame($question['question'], $parsed->questiontext);
        $this->assertEquals(FORMAT_PLAIN, $parsed->questiontextformat);
        foreach ($question['options'] as $index => $option) {
            $this->assertSame($option, $parsed->answer[$index]['text']);
            $this->assertEquals($index === 2 ? 1 : 0, $parsed->fraction[$index]);
        }
    }

    /**
     * Prefix formula-like cells and preserve quotes, commas, Unicode and slashes.
     */
    public function test_csv_round_trip_neutralises_formulas(): void {
        $cells = ['=1+1', '+SUM(A1)', '-1+2', '@SUM(A1)', "\t=1+1", '  =1+1', 'ordinary', '"quoted", città \\'];
        $safe = assessment_export::csv_row($cells);
        foreach (array_slice($safe, 0, 6) as $cell) {
            $this->assertStringStartsWith("'", $cell);
        }
        $this->assertSame($cells[6], $safe[6]);
        $this->assertSame($cells[7], $safe[7]);
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, $safe, ',', '"', '');
        rewind($stream);
        $this->assertSame($safe, fgetcsv($stream, 0, ',', '"', ''));
        fclose($stream);
    }
}
