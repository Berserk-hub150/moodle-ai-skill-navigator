<?php

namespace local_aiskillnavigator\service\embedding;

defined('MOODLE_INTERNAL') || die();

// Searches indexed chunks with vector similarity or keyword fallback.
class embedding_searcher {
    private embedding_config $config;

    public function __construct(embedding_config $config) {
        $this->config = $config;
    }

    public function search(string $query, int $courseid, int $topk, int $materialid): array {
        $query = trim($query) !== '' ? trim($query) : 'course material concepts learning objectives';
        $topk = $topk > 0 ? $topk : 5;
        $chunks = (new chunk_repository())->load($courseid, $materialid);

        if (empty($chunks)) {
            return [];
        }

        $queryembedding = (new embedding_client($this->config))->generate($query);
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

    private function score(string $query, ?array $queryembedding, \stdClass $chunk): float {
        $chunkembedding = json_decode((string) $chunk->embedding, true);

        if ($queryembedding === null || !is_array($chunkembedding) || empty($chunkembedding)) {
            return (new keyword_similarity())->score($query, (string) $chunk->chunktext) * 0.5;
        }

        return (new vector_similarity())->cosine($queryembedding, $chunkembedding);
    }
}
