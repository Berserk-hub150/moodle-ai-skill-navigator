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

// Indexes one teacher material into RAG chunks.
/**
 * Embedding indexer implementation.
 */
class embedding_indexer {
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
     * Index helper.
     *
     * @param int $materialid Stored material ID.
     * @param int $courseid Moodle course ID.
     * @param string $title Title.
     * @param string $content Content to process.
     * @param bool $generateembeddings Generateembeddings.
     */
    public function index(
        int $materialid,
        int $courseid,
        string $title,
        string $content,
        bool $generateembeddings = true
    ): array {
        $content = trim($content);

        if ($content === '') {
            return ['success' => false, 'chunks' => 0, 'message' => 'Empty content, nothing to index.'];
        }

        $repo = new chunk_repository();
        $chunks = (new paragraph_chunker())->split($content);

        if (empty($chunks)) {
            return ['success' => false, 'chunks' => 0, 'message' => 'No chunks generated from this material.'];
        }

        return $this->store($repo, $chunks, $materialid, $courseid, $title, $generateembeddings);
    }

    /**
     * Store helper.
     *
     * @param chunk_repository $repo Repo.
     * @param array $chunks Material chunks.
     * @param int $materialid Stored material ID.
     * @param int $courseid Moodle course ID.
     * @param string $title Title.
     * @param bool $generateembeddings Generateembeddings.
     */
    private function store(
        chunk_repository $repo,
        array $chunks,
        int $materialid,
        int $courseid,
        string $title,
        bool $generateembeddings
    ): array {
        global $DB;
        $records = [];
        $indexed = 0;
        $failed = 0;
        $client = new embedding_client($this->config);
        $recordbuilder = new embedding_chunk_record($this->config);

        foreach ($chunks as $index => $chunktext) {
            $embedding = $generateembeddings ? $client->generate($chunktext) : null;

            if ($generateembeddings && $embedding === null) {
                $failed++;
            }

            $records[] = $recordbuilder->make($materialid, $courseid, $title, $index, $chunktext, $embedding ?? []);
            $indexed++;
        }

        $transaction = $DB->start_delegated_transaction();
        try {
            $repo->delete_material($materialid);
            foreach ($records as $record) {
                $repo->insert($record);
            }
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }

        $message = "Indexed {$indexed} chunks from \"{$title}\".";

        if (!$generateembeddings) {
            $message .= ' Embeddings were skipped; keyword fallback is active.';
        } else if ($failed > 0) {
            $message .= " {$failed} chunks were saved without embeddings and will use keyword fallback.";
        }

        return ['success' => true, 'chunks' => $indexed, 'message' => $message];
    }
}
