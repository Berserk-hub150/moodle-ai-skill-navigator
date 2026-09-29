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

/**
 * Read graph evidence only from sources available to the current user and provider.
 */
class knowledge_graph_reader {
    /**
     * Return a graph built from permitted source rows, never a cached global description.
     *
     * @param int $courseid Owning course.
     * @param int $limitnodes Maximum nodes.
     * @param int $limitedges Maximum relations.
     * @return array Nodes and edges.
     */
    public static function read(int $courseid, int $limitnodes = 80, int $limitedges = 160): array {
        global $DB;
        require_once(__DIR__ . '/../../includes/material_ai_policy.php');
        $materials = $DB->get_records('local_aiskillnavigator_material', ['courseid' => $courseid]);
        $allowed = [];
        foreach ($materials as $material) {
            if (
                material_access::can_read($material)
                    && local_aiskillnavigator_material_can_be_sent_to_current_ai($material)
            ) {
                $allowed[] = (int)$material->id;
            }
        }
        if (!$allowed) {
            return ['nodes' => [], 'edges' => []];
        }
        [$insql, $params] = $DB->get_in_or_equal($allowed, SQL_PARAMS_NAMED, 'graphmaterial');
        $params['courseid'] = $courseid;
        $sources = $DB->get_records_sql(
            "SELECT s.id, s.conceptid, s.evidence, c.name
               FROM {local_aiskillnavigator_kg_source} s
               JOIN {local_aiskillnavigator_kg_concept} c ON c.id = s.conceptid
              WHERE c.courseid = :courseid AND s.materialid $insql
           ORDER BY s.id ASC",
            $params
        );
        $nodes = [];
        foreach ($sources as $source) {
            $id = (int)$source->conceptid;
            if (!isset($nodes[$id])) {
                $nodes[$id] = [
                    'id' => $id,
                    'label' => (string)$source->name,
                    'confidence' => 0,
                    'sources' => 0,
                    'relations' => 0,
                    'description' => \core_text::substr((string)$source->evidence, 0, 300),
                ];
            }
            $nodes[$id]['sources']++;
        }
        $relations = $DB->get_records_sql(
            "SELECT r.* FROM {local_aiskillnavigator_kg_relation} r
              WHERE r.courseid = :courseid AND r.materialid $insql
           ORDER BY r.confidence DESC, r.id DESC",
            $params
        );
        foreach ($relations as $relation) {
            if (isset($nodes[$relation->sourceconceptid], $nodes[$relation->targetconceptid])) {
                $nodes[$relation->sourceconceptid]['relations']++;
                $nodes[$relation->targetconceptid]['relations']++;
            }
        }
        uasort($nodes, static fn($a, $b) => ($b['sources'] <=> $a['sources'])
            ?: ($b['relations'] <=> $a['relations']) ?: strcmp($a['label'], $b['label']));
        $nodes = array_slice($nodes, 0, max(1, min(500, $limitnodes)), true);
        $edges = [];
        foreach ($relations as $relation) {
            if (!isset($nodes[$relation->sourceconceptid], $nodes[$relation->targetconceptid])) {
                continue;
            }
            $edges[] = [
                'id' => (int)$relation->id,
                'from' => (int)$relation->sourceconceptid,
                'to' => (int)$relation->targetconceptid,
                'type' => (string)$relation->relationtype,
                'confidence' => (int)$relation->confidence,
                'evidence' => \core_text::substr((string)$relation->evidence, 0, 300),
            ];
            if (count($edges) >= max(1, min(1000, $limitedges))) {
                break;
            }
        }
        return ['nodes' => array_values($nodes), 'edges' => $edges];
    }
}
