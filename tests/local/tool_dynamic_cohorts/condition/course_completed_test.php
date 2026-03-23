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

namespace tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition;

use tool_dynamic_cohorts\condition_base;
use tool_dynamic_cohorts\rule;
use tool_dynamic_cohorts\rule_manager;

/**
 * Unit tests for course_completed condition class.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers     \tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\course_completed
 */
final class course_completed_test extends \advanced_testcase {
    /**
     * Get condition instance for testing.
     *
     * @param array $configdata Config data to be set.
     * @return condition_base
     */
    protected function get_condition(array $configdata = []): condition_base {
        $condition = condition_base::get_instance(0, (object)[
            'classname' => '\tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\course_completed',
        ]);
        $condition->set_config_data($configdata);

        return $condition;
    }

    /**
     * Test retrieving of config data.
     */
    public function test_retrieving_configdata(): void {
        $formdata = (object)[
            'courseid' => 1,
            'operator' => 3,
            'timecompleted' => 777777,
            'ruleid' => 1,
            'sortorder' => 0,
        ];

        $actual = $this->get_condition()::retrieve_config_data($formdata);
        $expected = [
            'courseid' => 1,
            'operator' => 3,
            'timecompleted' => 777777,
        ];
        $this->assertEquals($expected, $actual);
    }

    /**
     * Test setting and getting config data.
     */
    public function test_set_and_get_configdata(): void {
        $condition = $this->get_condition([
            'courseid' => 1,
            'operator' => 3,
            'timecompleted' => 777777,
        ]);

        $this->assertEquals(
            [
                'courseid' => 1,
                'operator' => 3,
                'timecompleted' => 777777,
            ],
            $condition->get_config_data()
        );
    }

    /**
     * Test getting config description.
     */
    public function test_config_description(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $now = time();

        $condition = $this->get_condition([
            'courseid' => $course->id,
            'operator' => course_completed::OPERATOR_AFTER,
            'timecompleted' => $now,
        ]);

        $this->assertSame(
            'Users who have completed course "' . $course->fullname . '" after ' . userdate($now),
            $condition->get_config_description(),
        );

        $condition = $this->get_condition([
            'courseid' => $course->id,
            'operator' => course_completed::OPERATOR_BEFORE,
            'timecompleted' => $now,
        ]);

        $this->assertSame(
            'Users who have completed course "' . $course->fullname . '" before ' . userdate($now),
            $condition->get_config_description(),
        );

        $condition = $this->get_condition([
            'courseid' => $course->id,
            'operator' => course_completed::OPERATOR_ANY,
            'timecompleted' => $now,
        ]);

        $this->assertSame(
            'Users who have completed course "' . $course->fullname . '"  ',
            $condition->get_config_description(),
        );
    }

    /**
     * Test getting rule.
     */
    public function test_get_rule(): void {
        $this->resetAfterTest();

        // Rule is not set.
        $condition = $this->get_condition();
        $this->assertNull($condition->get_rule());

        // Create a rule and set it to an instance.
        $rule = new rule(0, (object)['name' => 'Test rule 1']);
        $rule->save();

        $condition = cohort_membership::get_instance(0, (object)['ruleid' => $rule->get('id')]);
        $this->assertEquals($condition->get_rule()->get('id'), $rule->get('id'));
    }

    /**
     * Test is broken.
     */
    public function test_is_broken_and_broken_description(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();

        $condition = condition_base::get_instance(0, (object)[
            'classname' => '\tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\course_completed',
        ]);

        $this->assertFalse($condition->is_broken());

        // Invalid course.
        $condition = $this->get_condition([
            'courseid' => 7777,
            'operator' => course_completed::OPERATOR_ANY,
            'timecompleted' => 0,
        ]);
        $this->assertTrue($condition->is_broken());
        $this->assertSame('Missing course', $condition->get_broken_description());

        // Completion is disabled.
        $condition = $this->get_condition([
            'courseid' => $course->id,
            'operator' => course_completed::OPERATOR_ANY,
            'timecompleted' => 0,
        ]);
        $this->assertTrue($condition->is_broken());
        $this->assertSame('Completion is disabled for configured course', $condition->get_broken_description());

        // Completion is enabled.
        $DB->set_field('course', 'enablecompletion', 1, ['id' => $course->id]);
        $condition = $this->get_condition([
            'courseid' => $course->id,
            'operator' => course_completed::OPERATOR_ANY,
            'timecompleted' => 0,
        ]);
        $this->assertFalse($condition->is_broken());
    }

    /**
     * Test getting correct SQL.
     */
    public function test_get_sql_data(): void {
        global $DB;

        $this->resetAfterTest();

        $now = time();

        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($user1->id, $course->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, $studentrole->id);

        $completionuser1 = new \completion_completion(['userid' => $user1->id, 'course' => $course->id]);
        $completionuser1->mark_complete($now + WEEKSECS);

        $completionuser2 = new \completion_completion(['userid' => $user2->id, 'course' => $course->id]);
        $completionuser2->mark_complete($now - WEEKSECS);

        $totalusers = $DB->count_records('user');
        $this->assertTrue($totalusers > 2);

        // Any completion. Should get user 1 and user 2.
        $condition = $this->get_condition([
            'courseid' => $course->id,
            'operator' => course_completed::OPERATOR_ANY,
            'timecompleted' => 0,
        ]);

        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $this->assertCount(2, $DB->get_records_sql($sql, $result->get_params()));

        // Before. Should get user 2.
        $condition = $this->get_condition([
            'courseid' => $course->id,
            'operator' => course_completed::OPERATOR_BEFORE,
            'timecompleted' => $now,
        ]);

        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $actual = $DB->get_records_sql($sql, $result->get_params());
        $this->assertCount(1, $actual);
        $this->assertSame($user2->id, reset($actual)->id);

        // After. Should get user 1.
        $condition = $this->get_condition([
            'courseid' => $course->id,
            'operator' => course_completed::OPERATOR_AFTER,
            'timecompleted' => $now,
        ]);

        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $actual = $DB->get_records_sql($sql, $result->get_params());
        $this->assertCount(1, $actual);
        $this->assertSame($user1->id, reset($actual)->id);
    }

    /**
     * Test events that the condition is listening to.
     */
    public function test_get_events(): void {
        $this->assertEquals([], $this->get_condition()->get_events());
    }

    /**
     * Test can_merge() returns true only for OPERATOR_ANY on non-broken conditions.
     */
    public function test_can_merge(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        // OPERATOR_ANY on a valid course: mergeable.
        $condition = $this->get_condition([
            'courseid' => $course->id,
            'operator' => course_completed::OPERATOR_ANY,
            'timecompleted' => 0,
        ]);
        $this->assertTrue($condition->can_merge());

        // OPERATOR_BEFORE: not mergeable (date-bounded).
        $condition = $this->get_condition([
            'courseid' => $course->id,
            'operator' => course_completed::OPERATOR_BEFORE,
            'timecompleted' => time(),
        ]);
        $this->assertFalse($condition->can_merge());

        // OPERATOR_AFTER: not mergeable (date-bounded).
        $condition = $this->get_condition([
            'courseid' => $course->id,
            'operator' => course_completed::OPERATOR_AFTER,
            'timecompleted' => time(),
        ]);
        $this->assertFalse($condition->can_merge());

        // Broken condition (non-existent course): not mergeable.
        $condition = $this->get_condition([
            'courseid' => 99999,
            'operator' => course_completed::OPERATOR_ANY,
            'timecompleted' => 0,
        ]);
        $this->assertFalse($condition->can_merge());
    }

    /**
     * Test build_merged_sql() returns a 1=0 fallback for AND operator or empty input.
     */
    public function test_build_merged_sql_returns_fallback_for_non_or_operators(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $inst = $this->get_condition([
            'courseid' => $course->id,
            'operator' => course_completed::OPERATOR_ANY,
            'timecompleted' => 0,
        ]);

        // AND operator: not supported, returns 1=0.
        $result = course_completed::build_merged_sql([$inst], rule_manager::CONDITIONS_OPERATOR_AND);
        $this->assertEmpty($result->get_join());
        $this->assertSame('1=0', $result->get_where());
        $this->assertEmpty($result->get_params());

        // Empty instances array: returns 1=0.
        $result = course_completed::build_merged_sql([], rule_manager::CONDITIONS_OPERATOR_OR);
        $this->assertEmpty($result->get_join());
        $this->assertSame('1=0', $result->get_where());
        $this->assertEmpty($result->get_params());
    }

    /**
     * Test build_merged_sql() produces an EXISTS subquery with an IN list for OR rules.
     */
    public function test_build_merged_sql_uses_exists_with_in(): void {
        $this->resetAfterTest();

        $course1 = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $course2 = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        $inst1 = $this->get_condition([
            'courseid' => $course1->id,
            'operator' => course_completed::OPERATOR_ANY,
            'timecompleted' => 0,
        ]);
        $inst2 = $this->get_condition([
            'courseid' => $course2->id,
            'operator' => course_completed::OPERATOR_ANY,
            'timecompleted' => 0,
        ]);

        $result = course_completed::build_merged_sql([$inst1, $inst2], rule_manager::CONDITIONS_OPERATOR_OR);

        // No JOIN — all filtering is in the EXISTS subquery.
        $this->assertEmpty($result->get_join());

        // WHERE uses EXISTS … IN.
        $where = $result->get_where();
        $this->assertStringContainsStringIgnoringCase('EXISTS', $where);
        $this->assertStringContainsStringIgnoringCase('IN', $where);
        $this->assertStringContainsString('timecompleted IS NOT NULL', $where);

        // Both course IDs appear in the parameter list.
        $params = $result->get_params();
        $this->assertNotEmpty($params);
        $this->assertContainsEquals($course1->id, $params);
        $this->assertContainsEquals($course2->id, $params);
    }

    /**
     * Test build_merged_sql() returns the correct set of users from the database.
     */
    public function test_build_merged_sql_returns_correct_users(): void {
        global $DB;

        $this->resetAfterTest();

        $now = time();
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);

        $course1 = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $course2 = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        $user1 = $this->getDataGenerator()->create_user(); // completes course1 only
        $user2 = $this->getDataGenerator()->create_user(); // completes course2 only
        $user3 = $this->getDataGenerator()->create_user(); // completes neither

        $this->getDataGenerator()->enrol_user($user1->id, $course1->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course2->id, $studentrole->id);

        (new \completion_completion(['userid' => $user1->id, 'course' => $course1->id]))->mark_complete($now);
        (new \completion_completion(['userid' => $user2->id, 'course' => $course2->id]))->mark_complete($now);

        $inst1 = $this->get_condition([
            'courseid' => $course1->id,
            'operator' => course_completed::OPERATOR_ANY,
            'timecompleted' => 0,
        ]);
        $inst2 = $this->get_condition([
            'courseid' => $course2->id,
            'operator' => course_completed::OPERATOR_ANY,
            'timecompleted' => 0,
        ]);

        $result = course_completed::build_merged_sql([$inst1, $inst2], rule_manager::CONDITIONS_OPERATOR_OR);
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $rows = $DB->get_records_sql($sql, $result->get_params());

        $this->assertArrayHasKey($user1->id, $rows, 'User who completed course1 should be included');
        $this->assertArrayHasKey($user2->id, $rows, 'User who completed course2 should be included');
        $this->assertArrayNotHasKey($user3->id, $rows, 'User with no completions should be excluded');
    }
}
