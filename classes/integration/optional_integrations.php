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
 * Optional plugin integrations.
 *
 * @package    block_personalprogress
 * @copyright  2026 Eduardo Kraus
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_personalprogress\integration;

defined('MOODLE_INTERNAL') || die;

/**
 * Safe adapter for optional plugins.
 *
 * No optional class name is autoloaded until Moodle confirms that the plugin directory exists.
 * Integrations use public APIs only and never query another plugin's tables.
 */
class optional_integrations {
    /**
     * Streak summary.
     *
     * Expected public API: \block_personalstreak\api::get_summary($userid, $courseid).
     * get_streak() is accepted as a backwards-compatible alias.
     *
     * @param int $userid
     * @param int $courseid
     * @return array{available:bool,current:int,best:int}
     */
    public function get_streak(int $userid, int $courseid): array {
        $raw = $this->call_api('block', 'personalstreak', ['get_summary', 'get_streak'], [$userid, $courseid]);
        if ($raw === null) {
            return ['available' => false, 'current' => 0, 'best' => 0];
        }

        return [
            'available' => true,
            'current' => max(0, (int)($raw['current'] ?? $raw['streak'] ?? 0)),
            'best' => max(0, (int)($raw['best'] ?? $raw['record'] ?? 0)),
        ];
    }

    /**
     * Goals summary.
     *
     * Expected public API: \local_personalgoals\api::get_summary($userid, $courseid).
     *
     * @param int $userid
     * @param int $courseid
     * @return array{available:bool,active:int,completed:int}
     */
    public function get_goals(int $userid, int $courseid): array {
        $raw = $this->call_api('local', 'personalgoals', ['get_summary'], [$userid, $courseid]);
        if ($raw === null) {
            return ['available' => false, 'active' => 0, 'completed' => 0];
        }

        return [
            'available' => true,
            'active' => max(0, (int)($raw['active'] ?? $raw['total'] ?? 0)),
            'completed' => max(0, (int)($raw['completed'] ?? 0)),
        ];
    }

    /**
     * Quest summary.
     *
     * Expected public API: \local_xpquests\api::get_summary($userid, $courseid).
     *
     * @param int $userid
     * @param int $courseid
     * @return array{available:bool,active:int}
     */
    public function get_quests(int $userid, int $courseid): array {
        $raw = $this->call_api('local', 'xpquests', ['get_summary'], [$userid, $courseid]);
        if ($raw === null) {
            return ['available' => false, 'active' => 0];
        }

        return [
            'available' => true,
            'active' => max(0, (int)($raw['active'] ?? $raw['inprogress'] ?? 0)),
        ];
    }

    /**
     * Milestone summary.
     *
     * Expected public API: \local_xpmilestones\api::get_summary($userid, $courseid).
     *
     * @param int $userid
     * @param int $courseid
     * @return array{available:bool,earned:int}
     */
    public function get_milestones(int $userid, int $courseid): array {
        $raw = $this->call_api('local', 'xpmilestones', ['get_summary'], [$userid, $courseid]);
        if ($raw === null) {
            return ['available' => false, 'earned' => 0];
        }

        return [
            'available' => true,
            'earned' => max(0, (int)($raw['earned'] ?? $raw['count'] ?? 0)),
        ];
    }

    /**
     * Reward wallet summary.
     *
     * Expected public API: \local_rewardshop\api::get_wallet($userid, $courseid).
     * get_summary() is accepted as a compatible alias.
     *
     * @param int $userid
     * @param int $courseid
     * @return array{available:bool,credits:int}
     */
    public function get_wallet(int $userid, int $courseid): array {
        $raw = $this->call_api('local', 'rewardshop', ['get_wallet', 'get_summary'], [$userid, $courseid]);
        if ($raw === null) {
            return ['available' => false, 'credits' => 0];
        }

        return [
            'available' => true,
            'credits' => max(0, (int)($raw['credits'] ?? $raw['balance'] ?? 0)),
        ];
    }

    /**
     * Call the first public method available on an optional plugin API.
     *
     * @param string $plugintype
     * @param string $pluginname
     * @param array $methods
     * @param array $arguments
     * @return array|null
     */
    protected function call_api(string $plugintype, string $pluginname, array $methods, array $arguments): ?array {
        if (!$this->plugin_available($plugintype, $pluginname)) {
            return null;
        }

        $component = $plugintype . '_' . $pluginname;
        $class = '\\' . $component . '\\api';
        if (!class_exists($class)) {
            return null;
        }

        foreach ($methods as $method) {
            if (!method_exists($class, $method)) {
                continue;
            }
            try {
                $result = call_user_func_array([$class, $method], $arguments);
                return is_array($result) ? $result : null;
            } catch (\Throwable $exception) {
                debugging(
                    'Personal progress optional integration failed for ' . $component . ': ' . $exception->getMessage(),
                    DEBUG_DEVELOPER
                );
                return null;
            }
        }

        return null;
    }

    /**
     * Verify plugin availability without forcing optional autoloading.
     *
     * @param string $plugintype
     * @param string $pluginname
     * @return bool
     */
    protected function plugin_available(string $plugintype, string $pluginname): bool {
        return \core_component::get_plugin_directory($plugintype, $pluginname) !== null;
    }
}
