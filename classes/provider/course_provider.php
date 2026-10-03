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
 * Course progress provider.
 *
 * @package    block_personalprogress
 * @copyright  2026 Eduardo Kraus
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_personalprogress\provider;


/**
 * Uses Moodle core completion APIs.
 */
class course_provider {
    /**
     * Return course completion percentage when Moodle can calculate it.
     *
     * @param int $userid
     * @param int $courseid
     * @return array{available:bool,completion:int}
     */
    public function get_progress(int $userid, int $courseid): array {
        $course = get_course($courseid);
        $percentage = \core_completion\progress::get_course_progress_percentage($course, $userid);

        if ($percentage === null) {
            return [
                'available' => false,
                'completion' => 0,
            ];
        }

        return [
            'available' => true,
            'completion' => max(0, min(100, (int)round($percentage))),
        ];
    }
}
