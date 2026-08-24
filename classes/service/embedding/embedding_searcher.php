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

// Searches indexed chunks with vector similarity or keyword fallback.
/**
 * Embedding searcher implementation.
 */
class embedding_searcher {
    /** @var embedding_config Config. */
    private embedding_config $config;

    /**
     * Construct helper.
     */
    public function __construct(embedding_config $config) {
        $this->config = $config;
    }

    /**
     * Search helper.
     */
    public function search(
        string $query,
        int $courseid,
        int $topk,
        int $materialid,
        bool $generateembedding = true
    ): array {
        $query = trim($query) !== '' ? trim($query) : 'course material concepts learning objectives';
        $topk = $topk > 0 ? $topk : 5;
        $chunks = (new chunk_repository())->load($courseid, $materialid);

        if (empty($chunks)) {
            return [];
        }

        $queryembedding = $generateembedding
            ? (new embedding_client($this->config))->generate($query)
            : null;
        $scored = [];

        foreach ($chunks as $chunk) {
            $similarity = $this->score($query, $queryembedding, $chunk);
            $scored[] = (new search_result_builder())->make($chunk, $similarity);
        }

        usort($scored, function ($a, $b) {
            return $b->similarity <=> $a->similarity;
        });

        return array_slice($scored, 0, $topk);
    }

    /**
     * Score helper.
     */
    private function score(string $query, ?array $queryembedding, \stdClass $chunk): float {
        $chunkembedding = json_decode((string) $chunk->embedding, true);

        if ($queryembedding === null || !is_array($chunkembedding) || empty($chunkembedding)) {
            return (new keyword_similarity())->score($query, (string) $chunk->chunktext) * 0.5;
        }

        return (new vector_similarity())->cosine($queryembedding, $chunkembedding);
    }
}
