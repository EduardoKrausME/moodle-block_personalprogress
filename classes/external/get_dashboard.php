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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Aggregated dashboard AJAX endpoint.
 *
 * @package    block_personalprogress
 * @copyright  2026 Eduardo Kraus
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_personalprogress\external;

defined('MOODLE_INTERNAL') || die;

// Moodle 4.1 exposes external API classes globally, while newer branches use core_external.
// Create local compatibility aliases without forcing deprecated externallib.php on newer Moodle.
if (!class_exists('\\external_api')) {
    if (class_exists('\\core_external\\external_api')) {
        class_alias('\\core_external\\external_api', 'external_api');
        class_alias('\\core_external\\external_function_parameters', 'external_function_parameters');
        class_alias('\\core_external\\external_multiple_structure', 'external_multiple_structure');
        class_alias('\\core_external\\external_single_structure', 'external_single_structure');
        class_alias('\\core_external\\external_value', 'external_value');
    } else {
        global $CFG;
        require_once($CFG->libdir . '/externallib.php');
    }
}

use block_personalprogress\api;
use block_personalprogress\view\dashboard_builder;
use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;

/**
 * One-request dashboard endpoint.
 */
class get_dashboard extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'blockinstanceid' => new external_value(PARAM_INT, 'Personal progress block instance id'),
        ]);
    }

    /**
     * Execute.
     *
     * No userid is accepted from the browser: the AJAX endpoint always returns the current
     * authenticated user's own progress.
     *
     * @param int $courseid
     * @param int $blockinstanceid
     * @return array
     */
    public static function execute(int $courseid, int $blockinstanceid): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'blockinstanceid' => $blockinstanceid,
        ]);

        $coursecontext = \context_course::instance($params['courseid']);
        self::validate_context($coursecontext);
        api::require_access((int)$USER->id, $params['courseid']);

        $instance = $DB->get_record('block_instances', [
            'id' => $params['blockinstanceid'],
            'blockname' => 'personalprogress',
        ], 'id,blockname,configdata', MUST_EXIST);

        $config = self::decode_config((string)$instance->configdata);
        $dashboard = api::get_dashboard((int)$USER->id, $params['courseid']);
        return dashboard_builder::build($dashboard, $config);
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        $card = new external_single_structure([
            'key' => new external_value(PARAM_ALPHANUMEXT, 'Card key'),
            'label' => new external_value(PARAM_TEXT, 'Card label'),
            'value' => new external_value(PARAM_TEXT, 'Main value'),
            'secondary' => new external_value(PARAM_TEXT, 'Secondary text'),
            'hassecondary' => new external_value(PARAM_BOOL, 'Whether secondary text exists'),
            'showprogress' => new external_value(PARAM_BOOL, 'Whether the card has a progress bar'),
            'progress' => new external_value(PARAM_INT, 'Progress percentage'),
            'progresslabel' => new external_value(PARAM_TEXT, 'Accessible progress label'),
            'isempty' => new external_value(PARAM_BOOL, 'Whether this is an empty-state card'),
            'emptymessage' => new external_value(PARAM_TEXT, 'Empty-state message'),
        ]);

        $week = new external_single_structure([
            'label' => new external_value(PARAM_TEXT, 'Week label'),
            'value' => new external_value(PARAM_INT, 'XP in week'),
            'valueformatted' => new external_value(PARAM_TEXT, 'Formatted XP'),
            'height' => new external_value(PARAM_INT, 'Relative bar height'),
            'arialabel' => new external_value(PARAM_TEXT, 'Accessible bar label'),
        ]);

        $chart = new external_single_structure([
            'show' => new external_value(PARAM_BOOL, 'Whether chart is enabled'),
            'label' => new external_value(PARAM_TEXT, 'Chart title'),
            'arialabel' => new external_value(PARAM_TEXT, 'Accessible chart label', VALUE_DEFAULT, ''),
            'empty' => new external_value(PARAM_BOOL, 'Whether chart has no data'),
            'emptymessage' => new external_value(PARAM_TEXT, 'Chart empty-state message'),
            'weeks' => new external_multiple_structure($week),
        ]);

        return new external_single_structure([
            'coursename' => new external_value(PARAM_TEXT, 'Course name'),
            'cards' => new external_multiple_structure($card),
            'hascards' => new external_value(PARAM_BOOL, 'Whether there are cards'),
            'shownodata' => new external_value(PARAM_BOOL, 'Whether the user has no XP data yet'),
            'nodatamessage' => new external_value(PARAM_TEXT, 'Friendly no-data message'),
            'chart' => $chart,
        ]);
    }

    /**
     * Decode Moodle block configdata without loading another block object.
     *
     * @param string $configdata
     * @return \stdClass
     */
    private static function decode_config(string $configdata): \stdClass {
        if ($configdata === '') {
            return new \stdClass();
        }

        $decoded = base64_decode($configdata, true);
        if ($decoded === false) {
            return new \stdClass();
        }

        $config = @unserialize($decoded, ['allowed_classes' => [\stdClass::class]]);
        return $config instanceof \stdClass ? $config : new \stdClass();
    }
}
