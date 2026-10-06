<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Personal progress block.
 *
 * @package    block_personalprogress
 * @copyright  2026 Eduardo Kraus
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/blocks/moodleblock.class.php');

use block_personalprogress\api;
use block_personalprogress\view\dashboard_builder;


/**
 * Compact personal progress dashboard block.
 */
class block_personalprogress extends block_base {
    /**
     * Initialise the block.
     */
    public function init(): void {
        $this->title = get_string('pluginname', 'block_personalprogress');
    }

    /**
     * The block has per-instance configuration.
     *
     * @return bool
     */
    public function instance_allow_config(): bool {
        return true;
    }

    /**
     * Allow several instances when different card sets are useful.
     *
     * @return bool
     */
    public function instance_allow_multiple(): bool {
        return true;
    }

    /**
     * Supported page formats.
     *
     * @return array
     */
    public function applicable_formats(): array {
        return [
            'course-view' => true,
            'my' => true,
            'site' => true,
            'mod' => true,
        ];
    }

    /**
     * Build block content.
     *
     * The data itself is loaded with one aggregated AJAX request. This keeps the initial
     * page render light and avoids one request per optional integration.
     *
     * @return stdClass|null
     */
    public function get_content(): ?stdClass {
        global $COURSE, $USER;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->footer = '';

        if (!isloggedin() || isguestuser()) {
            $this->content->text = '';
            return $this->content;
        }

        $course = api::resolve_course_for_page((int)$USER->id, $COURSE);
        if ($course === null) {
            $this->content->text = html_writer::div(
                get_string('nocourseavailable', 'block_personalprogress'),
                'block-personalprogress-empty'
            );
            return $this->content;
        }

        $coursecontext = context_course::instance((int)$course->id);
        if (!has_capability('block/personalprogress:view', $coursecontext)) {
            $this->content->text = '';
            return $this->content;
        }

        $rootid = html_writer::random_id('block-personalprogress-');
        $config = dashboard_builder::normalise_config($this->config ?? new stdClass());

        $templatedata = [
            'rootid' => $rootid,
            'coursename' => format_string($course->fullname),
            'loadinglabel' => get_string('loading', 'block_personalprogress'),
        ];

        $this->content->text = $this->page->get_renderer('core')->render_from_template(
            'block_personalprogress/container',
            $templatedata
        );

        $this->page->requires->js_call_amd('block_personalprogress/dashboard', 'init', [[
            'rootid' => $rootid,
            'courseid' => (int)$course->id,
            'blockinstanceid' => (int)$this->instance->id,
        ]]);

        if (!empty($config['showcoursetitle'])) {
            $this->content->footer = html_writer::span(
                format_string($course->fullname),
                'block-personalprogress-course'
            );
        }

        return $this->content;
    }
}
