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
 * API tests.
 *
 * @package    block_personalprogress
 * @copyright  2026 Eduardo Kraus
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_personalprogress;


use block_personalprogress\provider\personalxp_provider;
use local_personalxp\service\xp_manager;

/**
 * Aggregation, access, period and cache tests.
 *
 * @covers \block_personalprogress\api
 * @covers \block_personalprogress\provider\personalxp_provider
 */
final class api_test extends \advanced_testcase {
    /**
     * Create an enrolled student and make them current user.
     *
     * @param array $courseoptions
     * @param array $useroptions
     * @return array
     */
    private function create_fixture(array $courseoptions = [], array $useroptions = []): array {
        $course = $this->getDataGenerator()->create_course($courseoptions);
        $user = $this->getDataGenerator()->create_user($useroptions);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);
        return [$course, $user];
    }

    /**
     * A learner with no XP gets a valid zero state.
     */
    public function test_student_without_xp(): void {
        $this->resetAfterTest(true);
        [$course, $user] = $this->create_fixture();

        $dashboard = api::get_dashboard($user->id, $course->id);

        $this->assertSame(0, $dashboard['xp']['total']);
        $this->assertSame(1, $dashboard['xp']['level']);
    }

    /**
     * XP comes from local_personalxp, not from duplicated block storage.
     */
    public function test_student_with_xp(): void {
        $this->resetAfterTest(true);
        [$course, $user] = $this->create_fixture();

        $this->assertTrue(xp_manager::award(
            $user->id,
            $course->id,
            'phpunit',
            1,
            120,
            'PHPUnit award',
            'block_personalprogress',
            'phpunit'
        ));
        api::purge_cache($user->id, $course->id);

        $dashboard = api::get_dashboard($user->id, $course->id);
        $this->assertSame(120, $dashboard['xp']['total']);
    }

    /**
     * Course completion gracefully reports unavailable when the course does not use it.
     */
    public function test_course_without_completion(): void {
        $this->resetAfterTest(true);
        [$course, $user] = $this->create_fixture(['enablecompletion' => 0]);

        $dashboard = api::get_dashboard($user->id, $course->id);
        $this->assertFalse($dashboard['course']['available']);
        $this->assertSame(0, $dashboard['course']['completion']);
    }

    /**
     * The public API does not allow an ordinary learner to request somebody else's dashboard.
     */
    public function test_other_user_access_is_denied_without_capability(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $viewer = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($viewer->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($other->id, $course->id, 'student');
        $this->setUser($viewer);

        $this->expectException(\required_capability_exception::class);
        api::get_dashboard($other->id, $course->id);
    }

    /**
     * The view capability is enforced even for the current user.
     */
    public function test_view_capability_is_required(): void {
        global $DB;

        $this->resetAfterTest(true);
        [$course, $user] = $this->create_fixture();
        $context = \context_course::instance($course->id);
        $studentrole = (int)$DB->get_field('role', 'id', ['archetype' => 'student'], MUST_EXIST);
        assign_capability('block/personalprogress:view', CAP_PROHIBIT, $studentrole, $context->id, true);

        $this->expectException(\required_capability_exception::class);
        api::get_dashboard($user->id, $course->id);
    }

    /**
     * The short MUC cache is actually used and can be explicitly purged.
     */
    public function test_dashboard_cache_and_purge(): void {
        $this->resetAfterTest(true);
        [$course, $user] = $this->create_fixture();

        xp_manager::award(
            $user->id,
            $course->id,
            'phpunit-cache-a',
            10,
            10,
            'First',
            'block_personalprogress',
            'phpunit'
        );
        api::purge_cache($user->id, $course->id);
        $first = api::get_dashboard($user->id, $course->id);
        $this->assertSame(10, $first['xp']['total']);

        xp_manager::award(
            $user->id,
            $course->id,
            'phpunit-cache-b',
            11,
            20,
            'Second',
            'block_personalprogress',
            'phpunit'
        );
        $cached = api::get_dashboard($user->id, $course->id);
        $this->assertSame(10, $cached['xp']['total']);

        api::purge_cache($user->id, $course->id);
        $fresh = api::get_dashboard($user->id, $course->id);
        $this->assertSame(30, $fresh['xp']['total']);
    }

    /**
     * Weekly boundaries use the learner's Moodle timezone, not the server timezone.
     */
    public function test_weekly_comparison_respects_user_timezone(): void {
        global $DB;

        $this->resetAfterTest(true);
        [$course, $user] = $this->create_fixture([], ['timezone' => 'America/Sao_Paulo']);

        $previous = (new \DateTimeImmutable('2026-09-28 02:30:00', new \DateTimeZone('UTC')))->getTimestamp();
        $current = (new \DateTimeImmutable('2026-09-28 03:30:00', new \DateTimeZone('UTC')))->getTimestamp();
        $now = (new \DateTimeImmutable('2026-10-03 12:00:00', new \DateTimeZone('UTC')))->getTimestamp();

        $this->insert_xp_log($DB, $user->id, $course->id, 50, $previous, 'previous');
        $this->insert_xp_log($DB, $user->id, $course->id, 100, $current, 'current');

        $data = (new personalxp_provider())->get_dashboard($user->id, $course->id, $now);

        $this->assertTrue($data['period']['available']);
        $this->assertSame(100, $data['period']['current']);
        $this->assertSame(50, $data['period']['previous']);
        $this->assertSame(50, $data['period']['difference']);
        $this->assertCount(8, $data['chart']['weeks']);
    }

    /**
     * Insert immutable XP history for period tests.
     *
     * @param \moodle_database $db
     * @param int $userid
     * @param int $courseid
     * @param int $xp
     * @param int $timecreated
     * @param string $suffix
     */
    private function insert_xp_log(
        \moodle_database $db,
        int $userid,
        int $courseid,
        int $xp,
        int $timecreated,
        string $suffix
    ): void {
        $db->insert_record('local_personalxp_log', (object)[
            'userid' => $userid,
            'courseid' => $courseid,
            'rulekey' => 'phpunit-' . $suffix,
            'component' => 'block_personalprogress',
            'eventname' => 'phpunit',
            'objectid' => $timecreated,
            'xp' => $xp,
            'label' => 'PHPUnit ' . $suffix,
            'uniquehash' => hash('sha256', $userid . '|' . $courseid . '|' . $suffix . '|' . $timecreated),
            'timecreated' => $timecreated,
        ]);
    }
}
