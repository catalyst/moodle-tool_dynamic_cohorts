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

/**
 * Unit tests for course_completion condition class.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2026 Catalyst IT Australia Pty Ltd
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers     \tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\course_completion
 */
final class course_completion_test extends \advanced_testcase {
    /**
     * Get condition instance for testing.
     *
     * @param array $configdata Config data to be set.
     * @return condition_base
     */
    protected function get_condition(array $configdata = []): condition_base {
        $condition = condition_base::get_instance(0, (object)[
            'classname' => course_completion::class,
        ]);
        $condition->set_config_data($configdata);

        return $condition;
    }

    /**
     * Test retrieving of config data.
     */
    public function test_retrieving_configdata(): void {
        $formdata = (object)[
            'completionoperator' => course_completion::OPERATOR_HAVE_COMPLETED,
            'selectionoperator' => course_completion::SELECTION_ANY,
            'courseids' => [1, 2],
            'periodoperator' => course_completion::PERIOD_AFTER,
            'timecompleted' => 777777,
            'ruleid' => 1,
            'sortorder' => 0,
        ];

        $actual = $this->get_condition()::retrieve_config_data($formdata);
        $expected = [
            'completionoperator' => course_completion::OPERATOR_HAVE_COMPLETED,
            'selectionoperator' => course_completion::SELECTION_ANY,
            'courseids' => [1, 2],
            'periodoperator' => course_completion::PERIOD_AFTER,
            'timecompleted' => 777777,
        ];
        $this->assertEquals($expected, $actual);

        $formdata = (object) [
            'completionoperator' => course_completion::OPERATOR_HAVE_COMPLETED,
            'selectionoperator' => course_completion::SELECTION_ANY,
            'courseids' => [1, 2],
            'periodoperator' => course_completion::PERIOD_ANY,
            'timecompleted' => 777777,
            'ruleid' => 1,
            'sortorder' => 0,
        ];

        $actual = $this->get_condition()::retrieve_config_data($formdata);
        $expected = [
            'completionoperator' => course_completion::OPERATOR_HAVE_COMPLETED,
            'selectionoperator' => course_completion::SELECTION_ANY,
            'courseids' => [1, 2],
            'periodoperator' => course_completion::PERIOD_ANY,
            'timecompleted' => 0,
        ];
        $this->assertEquals($expected, $actual);
    }

    /**
     * Test setting and getting config data.
     */
    public function test_set_and_get_configdata(): void {
        $condition = $this->get_condition([
            'completionoperator' => course_completion::OPERATOR_HAVE_COMPLETED,
            'selectionoperator' => course_completion::SELECTION_ANY,
            'courseids' => [1, 2],
            'periodoperator' => course_completion::PERIOD_ANY,
            'timecompleted' => 0,
        ]);

        $this->assertEquals(
            [
                'completionoperator' => course_completion::OPERATOR_HAVE_COMPLETED,
                'selectionoperator' => course_completion::SELECTION_ANY,
                'courseids' => [1, 2],
                'periodoperator' => course_completion::PERIOD_ANY,
                'timecompleted' => 0,
            ],
            $condition->get_config_data(),
        );
    }

    /**
     * Test getting config description.
     */
    public function test_config_description(): void {
        $this->resetAfterTest();

        $course1 = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $course2 = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        $condition = $this->get_condition([
            'completionoperator' => course_completion::OPERATOR_HAVE_COMPLETED,
            'selectionoperator' => course_completion::SELECTION_ALL,
            'courseids' => [$course1->id, $course2->id],
            'periodoperator' => course_completion::PERIOD_BEFORE,
            'timecompleted' => time(),
        ]);

        $description = $condition->get_config_description();
        $this->assertStringContainsString('Users who have completed all of the following courses before', $description);
        $this->assertStringContainsString($course1->fullname, $description);
        $this->assertStringContainsString($course2->fullname, $description);
        $this->assertStringContainsString('/course/view.php?id=' . $course1->id, $description);
        $this->assertStringContainsString('/course/view.php?id=' . $course2->id, $description);
    }

    /**
     * Test is broken.
     */
    public function test_is_broken_and_broken_description(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();

        $condition = condition_base::get_instance(0, (object)[
            'classname' => '\\tool_dynamic_cohorts\\local\\tool_dynamic_cohorts\\condition\\course_completion',
        ]);

        $this->assertFalse($condition->is_broken());

        // Invalid course.
        $condition = $this->get_condition([
            'completionoperator' => course_completion::OPERATOR_HAVE_COMPLETED,
            'selectionoperator' => course_completion::SELECTION_ANY,
            'courseids' => [7777],
            'periodoperator' => course_completion::PERIOD_ANY,
            'timecompleted' => 0,
        ]);
        $this->assertTrue($condition->is_broken());
        $this->assertSame('Missing course', $condition->get_broken_description());

        // Completion is disabled.
        $condition = $this->get_condition([
            'completionoperator' => course_completion::OPERATOR_HAVE_COMPLETED,
            'selectionoperator' => course_completion::SELECTION_ANY,
            'courseids' => [$course->id],
            'periodoperator' => course_completion::PERIOD_ANY,
            'timecompleted' => 0,
        ]);
        $this->assertTrue($condition->is_broken());
        $this->assertSame('Completion is disabled for configured course', $condition->get_broken_description());

        // Completion is enabled.
        $DB->set_field('course', 'enablecompletion', 1, ['id' => $course->id]);
        $condition = $this->get_condition([
            'completionoperator' => course_completion::OPERATOR_HAVE_COMPLETED,
            'selectionoperator' => course_completion::SELECTION_ANY,
            'courseids' => [$course->id],
            'periodoperator' => course_completion::PERIOD_ANY,
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

        $course1 = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $course2 = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        $userall = $this->getDataGenerator()->create_user();
        $userone = $this->getDataGenerator()->create_user();
        $usernone = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($userall->id, $course1->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($userall->id, $course2->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($userone->id, $course1->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($userone->id, $course2->id, $studentrole->id);

        $completionall1 = new \completion_completion(['userid' => $userall->id, 'course' => $course1->id]);
        $completionall1->mark_complete($now - WEEKSECS);
        $completionall2 = new \completion_completion(['userid' => $userall->id, 'course' => $course2->id]);
        $completionall2->mark_complete($now + WEEKSECS);

        $completionone = new \completion_completion(['userid' => $userone->id, 'course' => $course1->id]);
        $completionone->mark_complete($now - WEEKSECS);

        $baseconfig = [
            'courseids' => [$course1->id, $course2->id],
            'periodoperator' => course_completion::PERIOD_ANY,
            'timecompleted' => 0,
        ];

        // Have completed any course.
        $condition = $this->get_condition($baseconfig + [
            'completionoperator' => course_completion::OPERATOR_HAVE_COMPLETED,
            'selectionoperator' => course_completion::SELECTION_ANY,
        ]);
        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $actual = $DB->get_records_sql($sql, $result->get_params());
        $this->assertArrayHasKey($userall->id, $actual);
        $this->assertArrayHasKey($userone->id, $actual);
        $this->assertArrayNotHasKey($usernone->id, $actual);

        // Have completed all courses.
        $condition = $this->get_condition($baseconfig + [
            'completionoperator' => course_completion::OPERATOR_HAVE_COMPLETED,
            'selectionoperator' => course_completion::SELECTION_ALL,
        ]);
        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $actual = $DB->get_records_sql($sql, $result->get_params());
        $this->assertArrayHasKey($userall->id, $actual);
        $this->assertArrayNotHasKey($userone->id, $actual);
        $this->assertArrayNotHasKey($usernone->id, $actual);

        // Have not completed any course.
        $condition = $this->get_condition($baseconfig + [
            'completionoperator' => course_completion::OPERATOR_HAVE_NOT_COMPLETED,
            'selectionoperator' => course_completion::SELECTION_ANY,
        ]);
        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $actual = $DB->get_records_sql($sql, $result->get_params());
        $this->assertArrayNotHasKey($userall->id, $actual);
        $this->assertArrayNotHasKey($userone->id, $actual);
        $this->assertArrayHasKey($usernone->id, $actual);

        // Have not completed all courses.
        $condition = $this->get_condition($baseconfig + [
            'completionoperator' => course_completion::OPERATOR_HAVE_NOT_COMPLETED,
            'selectionoperator' => course_completion::SELECTION_ALL,
        ]);
        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $actual = $DB->get_records_sql($sql, $result->get_params());
        $this->assertArrayNotHasKey($userall->id, $actual);
        $this->assertArrayHasKey($userone->id, $actual);
        $this->assertArrayHasKey($usernone->id, $actual);

        // Date filtering should only count completions before now.
        $condition = $this->get_condition([
            'completionoperator' => course_completion::OPERATOR_HAVE_COMPLETED,
            'selectionoperator' => course_completion::SELECTION_ALL,
            'courseids' => [$course1->id, $course2->id],
            'periodoperator' => course_completion::PERIOD_BEFORE,
            'timecompleted' => $now,
        ]);
        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $actual = $DB->get_records_sql($sql, $result->get_params());
        $this->assertArrayNotHasKey($userall->id, $actual);
        $this->assertArrayNotHasKey($userone->id, $actual);
        $this->assertArrayNotHasKey($usernone->id, $actual);
    }

    /**
     * Test events that the condition is listening to.
     */
    public function test_get_events(): void {
        $this->assertEquals([
            '\\core\\event\\course_completed',
            '\\core\\event\\course_completion_updated',
        ], $this->get_condition()->get_events());
    }
}
