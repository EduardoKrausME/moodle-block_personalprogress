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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <https://www.gnu.org/licenses/>.

/**
 * Public aggregation API.
 *
 * @package    block_personalprogress
 * @copyright  2026 Eduardo Kraus
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_personalprogress;

defined('MOODLE_INTERNAL') || die;

use block_personalprogress\integration\optional_integrations;
use block_personalprogress\provider\course_provider;
use block_personalprogress\provider\personalxp_provider;

/**
 * Public API for personal progress consumers.
 */
class api {
    /**
     * Aggregate the learner dashboard.
     *
     * This method never calculates or awards XP. It only reads the source plugins through
     * their public APIs and Moodle core completion APIs.
     *
     * @param int $userid User whose own dashboard is requested.
     * @param int $courseid Course scope.
     * @return array
     */
    public static function get_dashboard(int $userid, int $courseid): array {
        self::require_access($userid, $courseid);

        $cache = \cache::make('block_personalprogress', 'dashboard');
        $key = self::cache_key($userid, $courseid);
        $cached = $cache->get($key);
        if (is_array($cached)) {
            return $cached;
        }

        $xp = (new personalxp_provider())->get_dashboard($userid, $courseid);
        $optional = new optional_integrations();
        $course = get_course($courseid);

        $dashboard = [
            'xp' => $xp['xp'],
            'period' => $xp['period'],
            'streak' => $optional->get_streak($userid, $courseid),
            'goals' => $optional->get_goals($userid, $courseid),
            'quests' => $optional->get_quests($userid, $courseid),
            'milestones' => $optional->get_milestones($userid, $courseid),
            'wallet' => $optional->get_wallet($userid, $courseid),
            'course' => (new course_provider())->get_progress($userid, $courseid),
            'chart' => $xp['chart'],
            'meta' => [
                'courseid' => (int)$course->id,
                'coursename' => format_string($course->fullname),
                'generatedat' => time(),
            ],
        ];

        $cache->set($key, $dashboard);
        return $dashboard;
    }

    /**
     * Delete one cached personal dashboard.
     *
     * Source plugins may call this after a user-facing state change when they want immediate
     * refresh; otherwise the cache naturally expires after a short TTL.
     *
     * @param int $userid
     * @param int $courseid
     */
    public static function purge_cache(int $userid, int $courseid): void {
        \cache::make('block_personalprogress', 'dashboard')->delete(self::cache_key($userid, $courseid));
    }

    /**
     * Resolve a useful course for course pages, Dashboard and site pages.
     *
     * On a real course page the current course wins. On Dashboard/site pages the most recently
     * accessed active enrolled course is used. This keeps the block useful without inventing a
     * cross-course XP total that Personal XP itself does not define.
     *
     * @param int $userid
     * @param \stdClass $pagecourse
     * @return \stdClass|null
     */
    public static function resolve_course_for_page(int $userid, \stdClass $pagecourse): ?\stdClass {
        global $DB;

        if ((int)$pagecourse->id > SITEID) {
            return get_course((int)$pagecourse->id);
        }

        $courses = enrol_get_users_courses($userid, true, 'id,fullname,shortname,visible');
        if (empty($courses)) {
            return null;
        }

        $courseids = array_map('intval', array_keys($courses));
        list($insql, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'c');
        $params['userid'] = $userid;
        $sql = "SELECT courseid, timeaccess
                  FROM {user_lastaccess}
                 WHERE userid = :userid
                   AND courseid {$insql}
              ORDER BY timeaccess DESC";
        $recent = $DB->get_records_sql($sql, $params, 0, 1);

        if ($recent) {
            $record = reset($recent);
            $selectedid = (int)$record->courseid;
            if (isset($courses[$selectedid])) {
                return $courses[$selectedid];
            }
        }

        return reset($courses) ?: null;
    }

    /**
     * Enforce privacy and course access.
     *
     * @param int $userid
     * @param int $courseid
     */
    public static function require_access(int $userid, int $courseid): void {
        global $USER;

        $context = \context_course::instance($courseid);
        require_capability('block/personalprogress:view', $context);
        require_capability('moodle/course:view', $context);

        if ($userid !== (int)$USER->id) {
            require_capability('block/personalprogress:viewothers', $context);
        }
    }

    /**
     * Cache key.
     *
     * @param int $userid
     * @param int $courseid
     * @return string
     */
    private static function cache_key(int $userid, int $courseid): string {
        return 'u' . $userid . 'c' . $courseid;
    }
}
