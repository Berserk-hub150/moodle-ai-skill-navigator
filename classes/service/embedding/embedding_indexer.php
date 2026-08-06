<?php

namespace local_aiskillnavigator\service\embedding;

defined('MOODLE_INTERNAL') || die();

// Indexes one teacher material into RAG chunks.
class embedding_indexer {
    private embedding_config $config;

    public function __construct(embedding_config $config) {
        $this->config = $config;
    }

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
        $repo->delete_material($materialid);
        $chunks = (new paragraph_chunker())->split($content);

        if (empty($chunks)) {
            return ['success' => false, 'chunks' => 0, 'message' => 'No chunks generated from this material.'];
        }

        return $this->store($repo, $chunks, $materialid, $courseid, $title, $generateembeddings);
    }

    private function store(
        chunk_repository $repo,
        array $chunks,
        int $materialid,
        int $courseid,
        string $title,
        bool $generateembeddings
    ): array {
        $indexed = 0; $failed = 0;
        $client = new embedding_client($this->config);
        $recordbuilder = new embedding_chunk_record($this->config);

        foreach ($chunks as $index => $chunktext) {
            $embedding = $generateembeddings ? $client->generate($chunktext) : null;

            if ($generateembeddings && $embedding === null) {
                $failed++;
            }

            $repo->insert($recordbuilder->make($materialid, $courseid, $title, $index, $chunktext, $embedding ?? []));
            $indexed++;
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
