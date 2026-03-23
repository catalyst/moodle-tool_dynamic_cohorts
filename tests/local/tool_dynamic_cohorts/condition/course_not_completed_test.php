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

/**
 * Unit tests for course_not_completed condition class.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers     \tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\course_not_completed
 */
final class course_not_completed_test extends \advanced_testcase {
    /**
     * Get condition instance for testing.
     *
     * @param array $configdata Config data to be set.
     * @return condition_base
     */
    protected function get_condition(array $configdata = []): condition_base {
        $condition = condition_base::get_instance(0, (object)[
            'classname' => '\tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\course_not_completed',
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
            'ruleid' => 1,
            'sortorder' => 0,
        ];

        $actual = $this->get_condition()::retrieve_config_data($formdata);
        $expected = [
            'courseid' => 1,
        ];
        $this->assertEquals($expected, $actual);
    }

    /**
     * Test setting and getting config data.
     */
    public function test_set_and_get_configdata(): void {
        $condition = $this->get_condition([
            'courseid' => 1,
        ]);

        $this->assertEquals(
            [
                'courseid' => 1,
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

        $condition = $this->get_condition([
            'courseid' => $course->id,
        ]);

        $this->assertSame(
            'Users who have not completed course "' . $course->fullname . '"',
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
            'classname' => '\tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\course_not_completed',
        ]);

        $this->assertFalse($condition->is_broken());

        // Invalid course.
        $condition = $this->get_condition([
            'courseid' => 7777,
        ]);
        $this->assertTrue($condition->is_broken());
        $this->assertSame('Missing course', $condition->get_broken_description());

        // Completion is disabled.
        $condition = $this->get_condition([
            'courseid' => $course->id,
        ]);
        $this->assertTrue($condition->is_broken());
        $this->assertSame('Completion is disabled for configured course', $condition->get_broken_description());

        // Completion is enabled.
        $DB->set_field('course', 'enablecompletion', 1, ['id' => $course->id]);
        $condition = $this->get_condition([
            'courseid' => $course->id,
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
        $user3 = $this->getDataGenerator()->create_user();
        $user4 = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($user1->id, $course->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, $studentrole->id);

        $completionuser1 = new \completion_completion(['userid' => $user1->id, 'course' => $course->id]);
        $completionuser1->mark_complete($now + WEEKSECS);

        $completionuser2 = new \completion_completion(['userid' => $user2->id, 'course' => $course->id]);
        $completionuser2->mark_complete($now - WEEKSECS);

        $totalusers = $DB->count_records('user');
        $this->assertTrue($totalusers > 4);

        // Should get all users except user 1 and user 2.
        $condition = $this->get_condition([
            'courseid' => $course->id,
        ]);

        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $this->assertCount($totalusers - 2, $DB->get_records_sql($sql, $result->get_params()));
        $actual = $DB->get_records_sql($sql, $result->get_params());
        $this->assertArrayNotHasKey($user1->id, $actual);
        $this->assertArrayNotHasKey($user2->id, $actual);
        $this->assertArrayHasKey($user3->id, $actual);
        $this->assertArrayHasKey($user4->id, $actual);
    }

    /**
     * Test events that the condition is listening to.
     */
    public function test_get_events(): void {
        $this->assertEquals([], $this->get_condition()->get_events());
    }

    /**
     * Test that course_not_completed can never be merged, even when non-broken.
     *
     * course_not_completed extends course_completed but uses NOT-completed semantics;
     * merging multiple NOT-completed conditions under OR would produce incorrect SQL.
     */
    public function test_cannot_merge(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        $condition = $this->get_condition([
            'courseid'      => $course->id,
            'timecompleted' => 0,
        ]);

        $this->assertFalse($condition->can_merge());
    }
}
