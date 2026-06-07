<?php

namespace local_aiskillnavigator\service\embedding;

defined('MOODLE_INTERNAL') || die();

// Builds database records for indexed chunks.
class embedding_chunk_record {
    private embedding_config $config;

    public function __construct(embedding_config $config) {
        $this->config = $config;
    }

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
