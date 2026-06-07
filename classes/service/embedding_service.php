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

    public function index_material(int $materialid, int $courseid, string $title, string $content): array {
        return (new embedding\embedding_indexer($this->config))->index($materialid, $courseid, $title, $content);
    }

    public function delete_material_chunks(int $materialid): void {
        (new embedding\chunk_repository())->delete_material($materialid);
    }

    public function count_indexed_chunks(int $courseid, int $materialid = 0): int {
        return (new embedding\chunk_repository())->count($courseid, $materialid);
    }

    public function search(string $query, int $courseid, int $topk = 0, int $materialid = 0): array {
        return (new embedding\embedding_searcher($this->config))->search($query, $courseid, $topk, $materialid);
    }

    public function build_context(array $results, int $maxchars = 6000): string {
        return (new embedding\rag_context_builder())->build($results, $maxchars);
    }
}
