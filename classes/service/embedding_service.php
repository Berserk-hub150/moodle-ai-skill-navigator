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

namespace local_aiskillnavigator\service;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

foreach (glob(__DIR__ . '/embedding/*.php') as $file) {
    require_once($file);
}

// Public entry point for indexing and searching course materials.
/**
 * Embedding service implementation.
 */
class embedding_service {
    /** @var embedding\embedding_config Config. */
    private embedding\embedding_config $config;

    /**
     * Construct helper.
     */
    public function __construct() {
        $this->config = new embedding\embedding_config();
    }

    /**
     * Index material helper.
     */
    public function index_material(
        int $materialid,
        ?int $courseid = null,
        ?string $title = null,
        ?string $content = null
    ): array {
        global $DB;

        $material = $DB->get_record('local_aiskillnav_material', ['id' => $materialid]);

        if (!$material) {
            return ['success' => false, 'chunks' => 0, 'message' => 'Material not found.'];
        }

        $courseid = $courseid ?? (int)$material->courseid;
        $title = $title ?? (string)$material->title;
        $content = $content ?? (string)$material->content;

        if ($courseid !== (int)$material->courseid) {
            return ['success' => false, 'chunks' => 0, 'message' => 'Material does not belong to the requested course.'];
        }

        $generateembeddings = $this->can_generate_embeddings_for_material($material);

        return (new embedding\embedding_indexer($this->config))->index(
            $materialid,
            $courseid,
            $title,
            $content,
            $generateembeddings
        );
    }

    /**
     * Index material by id helper.
     */
    public function index_material_by_id(int $materialid): array {
        return $this->index_material($materialid);
    }

    /**
     * Delete material chunks helper.
     */
    public function delete_material_chunks(int $materialid): void {
        (new embedding\chunk_repository())->delete_material($materialid);
    }

    /**
     * Count indexed chunks helper.
     */
    public function count_indexed_chunks(int $courseid, int $materialid = 0): int {
        return (new embedding\chunk_repository())->count($courseid, $materialid);
    }

    /**
     * Search helper.
     */
    public function search(string $query, int $courseid, int $topk = 0, int $materialid = 0): array {
        $generateembedding = !$this->config->uses_external_service() || $this->external_ai_approved();

        return (new embedding\embedding_searcher($this->config))->search(
            $query,
            $courseid,
            $topk,
            $materialid,
            $generateembedding
        );
    }

    /**
     * Build context helper.
     */
    public function build_context(array $results, int $maxchars = 6000): string {
        return (new embedding\rag_context_builder())->build($results, $maxchars);
    }

    /**
     * Can generate embeddings for material helper.
     */
    private function can_generate_embeddings_for_material(\stdClass $material): bool {
        if ($this->config->is_keyword_only()) {
            return false;
        }

        if (!$this->config->uses_external_service()) {
            return true;
        }

        if (!$this->external_ai_approved()) {
            return false;
        }

        if (isset($material->externalaiallowed)) {
            return (int)$material->externalaiallowed === 1;
        }

        return isset($material->aipolicy) && (string)$material->aipolicy === 'external_allowed';
    }

    /**
     * External ai approved helper.
     */
    private function external_ai_approved(): bool {
        return (string)get_config('local_aiskillnavigator', 'externalaiapproved') === '1';
    }
}
