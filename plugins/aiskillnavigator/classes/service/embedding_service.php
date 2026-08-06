<?php

namespace local_aiskillnavigator\service;

defined('MOODLE_INTERNAL') || die();

foreach (glob(__DIR__ . '/embedding/*.php') as $file) {
    require_once($file);
}

// Public entry point for indexing and searching course materials.
class embedding_service {
    private embedding\embedding_config $config;

    public function __construct() {
        $this->config = new embedding\embedding_config();
    }

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

    public function index_material_by_id(int $materialid): array {
        return $this->index_material($materialid);
    }

    public function delete_material_chunks(int $materialid): void {
        (new embedding\chunk_repository())->delete_material($materialid);
    }

    public function count_indexed_chunks(int $courseid, int $materialid = 0): int {
        return (new embedding\chunk_repository())->count($courseid, $materialid);
    }

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

    public function build_context(array $results, int $maxchars = 6000): string {
        return (new embedding\rag_context_builder())->build($results, $maxchars);
    }

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

    private function external_ai_approved(): bool {
        return (string)get_config('local_aiskillnavigator', 'externalaiapproved') === '1';
    }
}
