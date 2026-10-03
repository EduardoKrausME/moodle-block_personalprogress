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
 * Optional integration tests.
 *
 * @package    block_personalprogress
 * @copyright  2026 Eduardo Kraus
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_personalprogress;

use block_personalprogress\integration\optional_integrations;

/**
 * Optional plugin presence/absence behaviour.
 *
 * @covers \block_personalprogress\integration\optional_integrations
 */
final class optional_integrations_test extends \advanced_testcase {
    /**
     * Missing plugins return unavailable sections and do not throw.
     */
    public function test_optional_plugins_absent(): void {
        $this->resetAfterTest(true);
        $adapter = $this->create_adapter([]);

        $this->assertFalse($adapter->get_streak(1, 2)['available']);
        $this->assertFalse($adapter->get_goals(1, 2)['available']);
        $this->assertFalse($adapter->get_quests(1, 2)['available']);
        $this->assertFalse($adapter->get_milestones(1, 2)['available']);
        $this->assertFalse($adapter->get_wallet(1, 2)['available']);
    }

    /**
     * Public API results are normalised into the stable block structure.
     */
    public function test_optional_plugins_present(): void {
        $this->resetAfterTest(true);
        $adapter = $this->create_adapter([
            'block_personalstreak' => ['current' => 6, 'best' => 14],
            'local_personalgoals' => ['active' => 3, 'completed' => 2],
            'local_xpquests' => ['active' => 1],
            'local_xpmilestones' => ['earned' => 12],
            'local_rewardshop' => ['credits' => 320],
        ]);

        $this->assertSame(['available' => true, 'current' => 6, 'best' => 14], $adapter->get_streak(1, 2));
        $this->assertSame(['available' => true, 'active' => 3, 'completed' => 2], $adapter->get_goals(1, 2));
        $this->assertSame(['available' => true, 'active' => 1], $adapter->get_quests(1, 2));
        $this->assertSame(['available' => true, 'earned' => 12], $adapter->get_milestones(1, 2));
        $this->assertSame(['available' => true, 'credits' => 320], $adapter->get_wallet(1, 2));
    }

    /**
     * Create an adapter fixture backed by supplied API responses.
     *
     * @param array $responses Responses keyed by component.
     * @return optional_integrations
     */
    private function create_adapter(array $responses): optional_integrations {
        return new class($responses) extends optional_integrations {
            /** @var array */
            private array $responses;

            /**
             * Store fixture responses.
             *
             * @param array $responses Responses keyed by component.
             */
            public function __construct(array $responses) {
                $this->responses = $responses;
            }

            /**
             * Return fixture responses keyed by plugin name.
             *
             * @param string $plugintype Plugin type.
             * @param string $pluginname Plugin name.
             * @param array $methods Candidate public methods.
             * @param array $arguments Method arguments.
             * @return array|null
             */
            protected function call_api(string $plugintype, string $pluginname, array $methods, array $arguments): ?array {
                return $this->responses[$plugintype . '_' . $pluginname] ?? null;
            }
        };
    }
}
