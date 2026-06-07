<?php

namespace local_aiskillnavigator\service\embedding;

defined('MOODLE_INTERNAL') || die();

// Creates search result objects for RAG context.
class search_result_builder {
    public function make(\stdClass $chunk, float $similarity): \stdClass {
        $result = new \stdClass();
        $result->id = (int) $chunk->id;
        $result->chunktext = (string) $chunk->chunktext;
        $result->title = (string) $chunk->title;
        $result->materialid = (int) $chunk->materialid;
        $result->chunkindex = (int) $chunk->chunkindex;
        $result->similarity = round($similarity, 4);

        return $result;
    }
}
