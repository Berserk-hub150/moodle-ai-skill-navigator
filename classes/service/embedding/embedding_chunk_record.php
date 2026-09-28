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

namespace local_aiskillnavigator\service\embedding;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

// Builds database records for indexed chunks.
/**
 * Embedding chunk record implementation.
 */
class embedding_chunk_record {
    /** @var embedding_config Config. */
    private embedding_config $config;

    /**
     * Construct helper.
     *
     * @param embedding_config $config Config.
     */
    public function __construct(embedding_config $config) {
        $this->config = $config;
    }

    /**
     * Make helper.
     *
     * @param int $materialid Stored material ID.
     * @param int $courseid Moodle course ID.
     * @param string $title Title.
     * @param int $index Index.
     * @param string $text Text to process.
     * @param array $embedding Embedding.
     */
    public function make(int $materialid, int $courseid, string $title, int $index, string $text, array $embedding): \stdClass {
        $record = new \stdClass();
        $record->materialid = $materialid;
        $record->courseid = $courseid;
        $record->title = $title;
        $record->chunkindex = $index;
        $record->chunktext = $text;
        $record->embedding = json_encode($embedding, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $record->embeddingmodel = $this->config->model;
        $record->timecreated = time();

        return $record;
    }
}
