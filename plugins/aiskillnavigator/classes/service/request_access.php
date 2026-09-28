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
 * Require a write capability, POST and a valid session key before a mutation.
 */
class request_access {
    /**
     * Validate a state-changing request.
     *
     * @param \context $context Course context.
     * @param string $capability Required write capability.
     */
    public static function require_write(\context $context, string $capability): void {
        require_capability($capability, $context);
        if (!data_submitted()) {
            throw new \moodle_exception('invalidrequestmethod', 'local_aiskillnavigator');
        }
        require_sesskey();
    }
}
