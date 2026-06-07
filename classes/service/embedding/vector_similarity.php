<?php

namespace local_aiskillnavigator\service\embedding;

defined('MOODLE_INTERNAL') || die();

// Computes cosine similarity between two vectors.
class vector_similarity {
    public function cosine(array $a, array $b): float {
        $dimensions = min(count($a), count($b));

        if ($dimensions === 0) {
            return 0.0;
        }

        $dot = 0.0;
        $norma = 0.0;
        $normb = 0.0;

        for ($i = 0; $i < $dimensions; $i++) {
            $va = (float) $a[$i];
            $vb = (float) $b[$i];
            $dot += $va * $vb;
            $norma += $va * $va;
            $normb += $vb * $vb;
        }

        $denominator = sqrt($norma) * sqrt($normb);

        return $denominator == 0.0 ? 0.0 : $dot / $denominator;
    }
}
