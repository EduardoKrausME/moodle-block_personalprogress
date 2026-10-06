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
 * View model tests.
 *
 * @package    block_personalprogress
 * @copyright  2026 Eduardo Kraus
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_personalprogress;


use block_personalprogress\view\dashboard_builder;

/**
 * Card visibility, ordering and friendly states.
 *
 * @covers \block_personalprogress\view\dashboard_builder
 */
final class view_builder_test extends \advanced_testcase {
    /**
     * Only available optional plugins produce optional cards.
     */
    public function test_only_personalxp_installed_hides_optional_cards(): void {
        $this->resetAfterTest(true);
        $dashboard = $this->base_dashboard();

        $view = dashboard_builder::build($dashboard, new \stdClass());
        $keys = array_column($view['cards'], 'key');

        $this->assertContains('xp', $keys);
        $this->assertContains('level', $keys);
        $this->assertNotContains('streak', $keys);
        $this->assertNotContains('goals', $keys);
        $this->assertNotContains('quests', $keys);
        $this->assertNotContains('milestones', $keys);
        $this->assertNotContains('wallet', $keys);
    }

    /**
     * Instance ordering is respected without changing source data.
     */
    public function test_card_order_configuration(): void {
        $this->resetAfterTest(true);
        $dashboard = $this->base_dashboard();
        $config = (object)[
            'orderxp' => 1,
            'orderlevel' => 2,
        ];

        $view = dashboard_builder::build($dashboard, $config);
        $keys = array_column($view['cards'], 'key');

        $this->assertSame('xp', $keys[0]);
        $this->assertSame('level', $keys[1]);
    }

    /**
     * Build the stable raw API fixture.
     *
     * @return array
     */
    private function base_dashboard(): array {
        return [
            'xp' => [
                'enabled' => true,
                'total' => 4850,
                'level' => 7,
                'levelname' => 'Level 7',
                'nextlevel' => 5000,
                'progress' => 97,
                'hasnextlevel' => true,
            ],
            'period' => ['available' => true, 'current' => 320, 'previous' => 250, 'difference' => 70],
            'streak' => ['available' => false, 'current' => 0, 'best' => 0],
            'goals' => ['available' => false, 'active' => 0, 'completed' => 0],
            'quests' => ['available' => false, 'active' => 0],
            'milestones' => ['available' => false, 'earned' => 0],
            'wallet' => ['available' => false, 'credits' => 0],
            'course' => ['available' => false, 'completion' => 0],
            'chart' => [
                'available' => true,
                'weeks' => [
                    ['label' => 'W1', 'xp' => 120],
                    ['label' => 'W2', 'xp' => 280],
                    ['label' => 'W3', 'xp' => 210],
                    ['label' => 'W4', 'xp' => 350],
                    ['label' => 'W5', 'xp' => 100],
                    ['label' => 'W6', 'xp' => 250],
                    ['label' => 'W7', 'xp' => 250],
                    ['label' => 'W8', 'xp' => 320],
                ],
            ],
            'meta' => ['courseid' => 2, 'coursename' => 'Course', 'generatedat' => time()],
        ];
    }
}
