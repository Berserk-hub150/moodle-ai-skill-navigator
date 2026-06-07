<?php

namespace local_aiskillnavigator\service;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/provider/ai_provider_config.php');
require_once(__DIR__ . '/provider/ai_provider_selector.php');

// Creates the configured AI provider.
class ai_provider_factory {
    public static function create_from_config(): ai_provider_interface {
        return (new provider\ai_provider_selector())->create(new provider\ai_provider_config());
    }
}
