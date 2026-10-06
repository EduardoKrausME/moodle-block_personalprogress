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
 * Personal XP data provider.
 *
 * @package    block_personalprogress
 * @copyright  2026 Eduardo Kraus
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_personalprogress\provider;


use DateTimeImmutable;
use local_personalxp\service\xp_manager;

/**
 * Reads Personal XP only through its public PHP API.
 */
class personalxp_provider {
    /** Number of weekly buckets displayed by the block. */
    private const WEEKS = 8;

    /**
     * Return the Personal XP dashboard fragment.
     *
     * @param int $userid
     * @param int $courseid
     * @param int|null $now Timestamp override used by tests.
     * @return array
     */
    public function get_dashboard(int $userid, int $courseid, ?int $now = null): array {
        $state = xp_manager::get_course_display_state($userid, $courseid);
        $totalxp = (int)($state['totalxp'] ?? 0);
        $levels = xp_manager::parse_levels();
        $levelnumber = 1;
        $nextthreshold = 0;

        foreach ($levels as $index => $level) {
            if ((int)$level['xp'] <= $totalxp) {
                $levelnumber = $index + 1;
                continue;
            }
            $nextthreshold = (int)$level['xp'];
            break;
        }

        $history = $this->build_history($userid, $courseid, $now);

        return [
            'xp' => [
                'enabled' => !empty($state['enabled']),
                'total' => $totalxp,
                'level' => $levelnumber,
                'levelname' => (string)($state['currentlevel'] ?? ''),
                'nextlevel' => $nextthreshold,
                'progress' => (int)($state['levelprogress'] ?? 0),
                'hasnextlevel' => $nextthreshold > 0,
            ],
            'period' => [
                'available' => $history['available'],
                'current' => $history['current'],
                'previous' => $history['previous'],
                'difference' => $history['current'] - $history['previous'],
            ],
            'chart' => [
                'available' => $history['available'],
                'weeks' => $history['weeks'],
            ],
        ];
    }

    /**
     * Build timezone-aware weekly buckets from the Personal XP public history API.
     *
     * @param int $userid
     * @param int $courseid
     * @param int|null $now
     * @return array
     */
    private function build_history(int $userid, int $courseid, ?int $now): array {
        $method = 'get_awards_between';
        if (!method_exists(xp_manager::class, $method)) {
            return [
                'available' => false,
                'current' => 0,
                'previous' => 0,
                'weeks' => [],
            ];
        }

        $user = \core_user::get_user($userid, 'id,timezone', MUST_EXIST);
        $timezone = \core_date::get_user_timezone_object($user);
        $now = $now ?? time();
        $nowdt = (new DateTimeImmutable('@' . $now))->setTimezone($timezone);
        $weekstart = $nowdt->setTime(0, 0, 0)->modify('monday this week');
        $firstweekstart = $weekstart->modify('-' . (self::WEEKS - 1) . ' weeks');
        $until = $now + 1;

        /** @var array $awards */
        $awards = xp_manager::{$method}($userid, $courseid, $firstweekstart->getTimestamp(), $until);

        $weeks = [];
        for ($i = 0; $i < self::WEEKS; $i++) {
            $start = $firstweekstart->modify('+' . $i . ' weeks');
            $end = $start->modify('+1 week');
            $weeks[] = [
                'start' => $start->getTimestamp(),
                'end' => $end->getTimestamp(),
                'label' => userdate(
                    $start->getTimestamp(),
                    get_string('strftimedateshort', 'langconfig'),
                    \core_date::get_user_timezone($user)
                ),
                'xp' => 0,
            ];
        }

        foreach ($awards as $award) {
            $timestamp = (int)($award['timecreated'] ?? 0);
            $xp = (int)($award['xp'] ?? 0);
            if ($timestamp <= 0 || $xp === 0) {
                continue;
            }
            foreach ($weeks as &$week) {
                if ($timestamp >= $week['start'] && $timestamp < $week['end']) {
                    $week['xp'] += $xp;
                    break;
                }
            }
            unset($week);
        }

        $current = (int)$weeks[self::WEEKS - 1]['xp'];
        $previous = (int)$weeks[self::WEEKS - 2]['xp'];

        foreach ($weeks as &$week) {
            unset($week['start'], $week['end']);
        }
        unset($week);

        return [
            'available' => true,
            'current' => $current,
            'previous' => $previous,
            'weeks' => $weeks,
        ];
    }
}
