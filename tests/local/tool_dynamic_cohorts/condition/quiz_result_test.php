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
 * Unit tests for quiz_result condition class.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\quiz_result
 */
final class quiz_result_test extends \advanced_testcase {
    /**
     * Returns condition instance for testing.
     *
     * @param array $configdata Config data to be set.
     * @return condition_base
     */
    protected function get_condition(array $configdata = []): condition_base {
        $condition = condition_base::get_instance(0, (object)[
            'classname' => '\\tool_dynamic_cohorts\\local\\tool_dynamic_cohorts\\condition\\quiz_result',
        ]);
        $condition->set_config_data($configdata);

        return $condition;
    }

    /**
     * Test retrieving config data.
     */
    public function test_retrieving_configdata(): void {
        $formdata = (object)[
            'quizid' => 12,
            'mingrade' => 31,
            'maxgrade' => 60,
            'ruleid' => 1,
            'sortorder' => 0,
        ];

        $actual = $this->get_condition()::retrieve_config_data($formdata);
        $expected = [
            'quizid' => 12,
            'mingrade' => 31,
            'maxgrade' => 60,
        ];
        $this->assertSame($expected, $actual);
    }

    /**
     * Test set and get config data.
     */
    public function test_set_and_get_configdata(): void {
        $condition = $this->get_condition([
            'quizid' => 12,
            'mingrade' => 0,
            'maxgrade' => 30,
        ]);

        $this->assertEquals(
            [
                'quizid' => 12,
                'mingrade' => 0,
                'maxgrade' => 30,
            ],
            $condition->get_config_data()
        );
    }

    /**
     * Test config description text.
     */
    public function test_config_description(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id,
            'name' => 'Score quiz',
            'grade' => 100,
        ]);

        $condition = $this->get_condition([
            'quizid' => $quiz->id,
            'mingrade' => 31,
            'maxgrade' => 60,
        ]);

        $description = $condition->get_config_description();
        $this->assertStringContainsString('Score quiz', $description);
        $this->assertStringContainsString('31', $description);
        $this->assertStringContainsString('60', $description);
    }

    /**
     * Test is broken and broken description.
     */
    public function test_is_broken_and_broken_description(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id,
            'grade' => 100,
        ]);

        $condition = $this->get_condition([
            'quizid' => $quiz->id,
            'mingrade' => 0,
            'maxgrade' => 30,
        ]);

        $this->assertFalse($condition->is_broken());

        $invalidquizcondition = $this->get_condition([
            'quizid' => 999999,
            'mingrade' => 0,
            'maxgrade' => 30,
        ]);
        $this->assertTrue($invalidquizcondition->is_broken());
        $this->assertSame('Missing quiz', $invalidquizcondition->get_broken_description());

        $DB->delete_records('grade_items', [
            'itemtype' => 'mod',
            'itemmodule' => 'quiz',
            'iteminstance' => $quiz->id,
        ]);

        $missinggradeitemcondition = $this->get_condition([
            'quizid' => $quiz->id,
            'mingrade' => 0,
            'maxgrade' => 30,
        ]);
        $this->assertTrue($missinggradeitemcondition->is_broken());
        $this->assertSame('Missing quiz grade item', $missinggradeitemcondition->get_broken_description());
    }

    /**
     * Test SQL result selection by score range.
     */
    public function test_get_sql_data(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id,
            'grade' => 100,
        ]);

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();

        // User 3 deliberately has no quiz grade and should be excluded.
        $DB->insert_record('quiz_grades', (object)[
            'quiz' => $quiz->id,
            'userid' => $user1->id,
            'grade' => 25,
            'timemodified' => time(),
        ]);

        $DB->insert_record('quiz_grades', (object)[
            'quiz' => $quiz->id,
            'userid' => $user2->id,
            'grade' => 45,
            'timemodified' => time(),
        ]);

        $condition = $this->get_condition([
            'quizid' => $quiz->id,
            'mingrade' => 0,
            'maxgrade' => 30,
        ]);

        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $actual = $DB->get_records_sql($sql, $result->get_params());
        $this->assertCount(1, $actual);
        $this->assertArrayHasKey($user1->id, $actual);

        $condition = $this->get_condition([
            'quizid' => $quiz->id,
            'mingrade' => 31,
            'maxgrade' => 60,
        ]);

        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $actual = $DB->get_records_sql($sql, $result->get_params());
        $this->assertCount(1, $actual);
        $this->assertArrayHasKey($user2->id, $actual);

        $condition = $this->get_condition([
            'quizid' => $quiz->id,
            'mingrade' => 61,
            'maxgrade' => 100,
        ]);

        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $actual = $DB->get_records_sql($sql, $result->get_params());
        $this->assertCount(0, $actual);
        $this->assertArrayNotHasKey($user3->id, $actual);
    }

    /**
     * Test events for condition.
     */
    public function test_get_events(): void {
        $this->assertEquals([], $this->get_condition()->get_events());
    }
}
