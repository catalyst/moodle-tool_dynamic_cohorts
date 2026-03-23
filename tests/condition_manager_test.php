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

namespace tool_dynamic_cohorts;

use core\event\user_created;
use tool_dynamic_cohorts\event\condition_created;
use tool_dynamic_cohorts\event\condition_deleted;
use tool_dynamic_cohorts\event\condition_updated;
use tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\course_completed;
use tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\course_not_completed;
use tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\user_profile;

/**
 * Tests for condition manager class.
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_dynamic_cohorts\condition_manager
 */
final class condition_manager_test extends \advanced_testcase {
    /**
     * Test all conditions.
     */
    public function test_get_all_conditions(): void {
        $conditions = condition_manager::get_all_conditions();
        $this->assertIsArray($conditions);
        $this->assertNotEmpty($conditions);

        foreach ($conditions as $condition) {
            $this->assertFalse(is_null($condition));
            $this->assertTrue(is_subclass_of($condition, condition_base::class));
            $this->assertFalse($condition->is_broken());
        }
    }

    /**
     * Test processing condition form
     */
    public function test_process_form(): void {
        global $DB;

        $this->resetAfterTest();
        $cohort = $this->getDataGenerator()->create_cohort();

        $rule = new rule(0, (object)['name' => 'Test rule', 'cohortid' => $cohort->id]);
        $rule->save();

        // Creating rule without conditions.
        $formdata = ['name' => 'Test', 'cohortid' => $cohort->id, 'description' => '',
            'conditionjson' => '', 'bulkprocessing' => 1];

        $eventsink = $this->redirectEvents();

        condition_manager::process_form($rule, (object)$formdata);

        $events = array_filter($eventsink->get_events(), function ($event) {
            return $event instanceof condition_created
                || $event instanceof condition_updated
                || $event instanceof condition_deleted;
        });

        $this->assertEmpty($events);
        $eventsink->clear();
        $this->assertEquals(1, $DB->count_records(rule::TABLE));
        $this->assertCount(0, $rule->get_condition_records());

        // Updating the rule with 3 new conditions, but flag isconditionschanged is not set.
        $conditionjson = json_encode([
            ['id' => 0, 'classname' => 'class1', 'sortorder' => 0, 'configdata' => ''],
            ['id' => 0, 'classname' => 'class2', 'sortorder' => 1, 'configdata' => ''],
            ['id' => 0, 'classname' => 'class3', 'sortorder' => 2, 'configdata' => ''],
        ]);

        $formdata = ['id' => $rule->get('id'), 'name' => 'Test', 'enabled' => 1, 'cohortid' => $cohort->id,
            'description' => '', 'conditionjson' => $conditionjson, 'bulkprocessing' => 1];

        condition_manager::process_form($rule, (object)$formdata);
        $this->assertCount(0, $rule->get_condition_records());

        $events = array_filter($eventsink->get_events(), function ($event) {
            return $event instanceof condition_created
                || $event instanceof condition_updated
                || $event instanceof condition_deleted;
        });
        $this->assertEmpty($events);
        $eventsink->clear();

        // Updating the rule with 3 new conditions. Expecting 3 new conditions to be created.
        $formdata = ['id' => $rule->get('id'), 'name' => 'Test', 'enabled' => 1, 'cohortid' => $cohort->id,
            'description' => '', 'conditionjson' => $conditionjson, 'isconditionschanged' => true, 'bulkprocessing' => 1];
        condition_manager::process_form($rule, (object)$formdata);

        $this->assertCount(3, $rule->get_condition_records());
        $events = array_filter($eventsink->get_events(), function ($event) {
            return $event instanceof condition_created;
        });
        $this->assertCount(3, $events);
        $eventsink->clear();

        $this->assertTrue(condition::record_exists_select('classname = ? AND ruleid = ?', ['class1', $rule->get('id')]));
        $this->assertTrue(condition::record_exists_select('classname = ? AND ruleid = ?', ['class2', $rule->get('id')]));
        $this->assertTrue(condition::record_exists_select('classname = ? AND ruleid = ?', ['class3', $rule->get('id')]));

        // Updating the rule with 1 new condition, 1 deleted condition (sortorder 1) and
        // two updated conditions (sortorder added to a class name). Expecting 1 new condition, 2 updated and 1 deleted.
        $conditions = $rule->get_condition_records();
        $conditionjson = [];

        foreach ($conditions as $condition) {
            if ($condition->get('sortorder') != 1) {
                $conditionjson[] = [
                    'id' => $condition->get('id'),
                    'classname' => $condition->get('classname') . $condition->get('sortorder'),
                    'sortorder' => $condition->get('sortorder'),
                    'configdata' => $condition->get('configdata'),
                ];
            }
        }

        $conditionjson[] = ['id' => 0, 'classname' => 'class4', 'sortorder' => 2, 'configdata' => ''];
        $conditionjson = json_encode($conditionjson);

        $formdata = ['id' => $rule->get('id'), 'name' => 'Test', 'enabled' => 1, 'cohortid' => $cohort->id,
            'description' => '', 'conditionjson' => $conditionjson, 'isconditionschanged' => true, 'bulkprocessing' => 1];

        condition_manager::process_form($rule, (object)$formdata);

        $this->assertCount(3, $rule->get_condition_records());

        $events = array_filter($eventsink->get_events(), function ($event) {
            return $event instanceof condition_created;
        });
        $this->assertCount(1, $events);

        $events = array_filter($eventsink->get_events(), function ($event) {
            return $event instanceof condition_updated;
        });
        $this->assertCount(2, $events);

        $events = array_filter($eventsink->get_events(), function ($event) {
            return $event instanceof condition_deleted;
        });
        $this->assertCount(1, $events);
        $eventsink->clear();

        $this->assertTrue(condition::record_exists_select('classname = ? AND ruleid = ?', ['class10', $rule->get('id')]));
        $this->assertFalse(condition::record_exists_select('classname = ? AND ruleid = ?', ['class2', $rule->get('id')]));
        $this->assertTrue(condition::record_exists_select('classname = ? AND ruleid = ?', ['class32', $rule->get('id')]));
        $this->assertTrue(condition::record_exists_select('classname = ? AND ruleid = ?', ['class4', $rule->get('id')]));
    }

    /**
     * Test delete_conditions.
     */
    public function test_delete_conditions(): void {
        global $DB;

        $this->resetAfterTest();

        $this->assertSame(0, $DB->count_records(condition::TABLE));

        $cohort = $this->getDataGenerator()->create_cohort(['component' => 'tool_dynamic_cohorts']);
        $rule = new rule(0, (object)['name' => 'Test rule', 'cohortid' => $cohort->id]);
        $rule->save();

        $condition1 = new condition(0, (object)['ruleid' => $rule->get('id'), 'classname' => 'test', 'sortorder' => 0]);
        $condition1->save();

        $condition2 = new condition(0, (object)['ruleid' => $rule->get('id'), 'classname' => 'test2', 'sortorder' => 1]);
        $condition2->save();

        $this->assertSame(2, $DB->count_records(condition::TABLE));

        $eventsink = $this->redirectEvents();

        condition_manager::delete_conditions([$condition1, $condition2, $rule, $cohort]);
        $this->assertSame(0, $DB->count_records(condition::TABLE));

        $events = array_filter($eventsink->get_events(), function ($event) {
            return $event instanceof condition_deleted;
        });

        $this->assertCount(2, $events);
        $eventsink->clear();
    }

    /**
     * Test getting conditions for a given event.
     */
    public function test_get_conditions_with_event(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();

        $event = user_created::create_from_userid($user->id);
        $conditions = condition_manager::get_conditions_with_event($event);

        $this->assertArrayHasKey('tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\user_custom_profile', $conditions);
        $this->assertArrayHasKey('tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\user_profile', $conditions);
    }

    /**
     * Basic test of building SQL data.
     */
    public function test_build_sql_data(): void {
        $this->resetAfterTest();

        $this->getDataGenerator()->create_user(['username' => 'user1username']);
        $this->getDataGenerator()->create_user(['username' => 'user2username']);
        $this->getDataGenerator()->create_user(['username' => 'test']);

        $cohort = $this->getDataGenerator()->create_cohort();

        $rule = new rule(0, (object)['name' => 'Test rule 1', 'cohortid' => $cohort->id,
            'operator' => rule_manager::CONDITIONS_OPERATOR_OR]);
        $rule->save();

        $conditions = [];

        $condition = user_profile::get_instance(0, (object)['ruleid' => $rule->get('id'), 'sortorder' => 1]);
        $condition->set_config_data([
            'profilefield' => 'username',
            'username_operator' => condition_base::TEXT_IS_EQUAL_TO,
            'username_value' => 'user1username',
        ]);
        $condition->get_record()->save();
        $conditions[] = $condition->get_record();

        $condition = user_profile::get_instance(0, (object)['ruleid' => $rule->get('id'), 'sortorder' => 1]);
        $condition->set_config_data([
            'profilefield' => 'username',
            'username_operator' => condition_base::TEXT_IS_EQUAL_TO,
            'username_value' => 'user2username',
        ]);
        $condition->get_record()->save();
        $conditions[] = $condition->get_record();

        $sql = condition_manager::build_sql_data($conditions);
        $this->assertEquals('', $sql->get_join());
        $this->assertStringNotContainsString('OR', $sql->get_where());
        $this->assertStringContainsString('AND ( u.deleted = 0)', $sql->get_where());
        $this->assertTrue(in_array('user1username', $sql->get_params()));
        $this->assertTrue(in_array('user2username', $sql->get_params()));
        $this->assertStringNotContainsString('AND ( u.id = ', $sql->get_where());
        $this->assertFalse(in_array(777, $sql->get_params()));

        $sql = condition_manager::build_sql_data($conditions, rule_manager::CONDITIONS_OPERATOR_OR);
        $this->assertEquals('', $sql->get_join());
        $this->assertStringContainsString('OR', $sql->get_where());
        $this->assertStringContainsString('AND ( u.deleted = 0)', $sql->get_where());
        $this->assertTrue(in_array('user1username', $sql->get_params()));
        $this->assertTrue(in_array('user2username', $sql->get_params()));
        $this->assertStringNotContainsString('AND ( u.id = ', $sql->get_where());
        $this->assertFalse(in_array(777, $sql->get_params()));

        $sql = condition_manager::build_sql_data($conditions, rule_manager::CONDITIONS_OPERATOR_OR, 777);
        $this->assertEquals('', $sql->get_join());
        $this->assertStringContainsString('OR', $sql->get_where());
        $this->assertStringContainsString('AND ( u.deleted = 0)', $sql->get_where());
        $this->assertStringContainsString('AND ( u.id = ', $sql->get_where());
        $this->assertTrue(in_array('user1username', $sql->get_params()));
        $this->assertTrue(in_array('user2username', $sql->get_params()));
        $this->assertTrue(in_array(777, $sql->get_params()));
    }

    /**
     * Test that generated SQL should exclude deleted users.
     */
    public function test_should_exclude_deleted_users(): void {
        global $DB;

        $this->resetAfterTest();

        $this->getDataGenerator()->create_user(['username' => 'user1username', 'auth' => 'lti']);
        $this->getDataGenerator()->create_user(['username' => 'user2username', 'auth' => 'lti']);
        $usertodeleted = $this->getDataGenerator()->create_user(['username' => 'user3username', 'auth' => 'lti']);
        $this->getDataGenerator()->create_user(['username' => 'test']);

        $cohort = $this->getDataGenerator()->create_cohort();

        $rule = new rule(0, (object)['name' => 'Test rule 1', 'cohortid' => $cohort->id,
            'operator' => rule_manager::CONDITIONS_OPERATOR_OR]);
        $rule->save();

        $conditions = [];
        $condition = user_profile::get_instance(0, (object)['ruleid' => $rule->get('id'), 'sortorder' => 1]);
        $condition->set_config_data([
            'profilefield' => 'username',
            'username_operator' => condition_base::TEXT_CONTAINS,
            'username_value' => 'username',
        ]);
        $condition->get_record()->save();
        $conditions[] = $condition->get_record();

        $condition = user_profile::get_instance(0, (object)['ruleid' => $rule->get('id'), 'sortorder' => 1]);
        $condition->set_config_data([
            'profilefield' => 'auth',
            'auth_operator' => condition_base::TEXT_IS_EQUAL_TO,
            'auth_value' => 'lti',
        ]);
        $condition->get_record()->save();
        $conditions[] = $condition->get_record();

        $sqldataand = condition_manager::build_sql_data($conditions);
        $sqldataor = condition_manager::build_sql_data($conditions, rule_manager::CONDITIONS_OPERATOR_OR);

        $basesql = "SELECT DISTINCT u.id FROM {user} u ";
        $sqland = $basesql . $sqldataand->get_join() . ' WHERE ' . $sqldataand->get_where();
        $sqlor = $basesql . $sqldataor->get_join() . ' WHERE ' . $sqldataor->get_where();

        $this->assertCount(3, $DB->get_records_sql($sqland, $sqldataand->get_params()));
        $this->assertCount(3, $DB->get_records_sql($sqlor, $sqldataor->get_params()));
        $this->assertArrayHasKey($usertodeleted->id, $DB->get_records_sql($sqland, $sqldataand->get_params()));
        $this->assertArrayHasKey($usertodeleted->id, $DB->get_records_sql($sqlor, $sqldataor->get_params()));

        delete_user($usertodeleted);

        $this->assertCount(2, $DB->get_records_sql($sqland, $sqldataand->get_params()));
        $this->assertCount(2, $DB->get_records_sql($sqlor, $sqldataor->get_params()));
        $this->assertArrayNotHasKey($usertodeleted->id, $DB->get_records_sql($sqland, $sqldataand->get_params()));
        $this->assertArrayNotHasKey($usertodeleted->id, $DB->get_records_sql($sqlor, $sqldataor->get_params()));
    }

    /**
     * Helper to create and save a course_completed condition record for build_sql_data tests.
     *
     * @param rule $rule
     * @param int $courseid
     * @param int $operator
     * @param int $timecompleted
     * @return condition
     */
    protected function make_course_completed_condition(
        rule $rule,
        int $courseid,
        int $operator = course_completed::OPERATOR_ANY,
        int $timecompleted = 0
    ): condition {
        $instance = course_completed::get_instance(0, (object)['ruleid' => $rule->get('id'), 'sortorder' => 1]);
        $instance->set_config_data([
            'courseid'      => $courseid,
            'operator'      => $operator,
            'timecompleted' => $timecompleted,
        ]);
        $instance->get_record()->save();
        return $instance->get_record();
    }

    /**
     * Multiple OPERATOR_ANY course_completed conditions under OR are merged into a single
     * EXISTS subquery — no cross-product JOINs.
     */
    public function test_build_sql_data_merges_course_completed_under_or(): void {
        global $DB;

        $this->resetAfterTest();

        $now = time();
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $cohort = $this->getDataGenerator()->create_cohort();

        $rule = new rule(0, (object)[
            'name'     => 'Merge OR rule',
            'cohortid' => $cohort->id,
            'operator' => rule_manager::CONDITIONS_OPERATOR_OR,
        ]);
        $rule->save();

        $course1 = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $course2 = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $course3 = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        // user1 completed course1, user2 completed course2, user3 completed none.
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($user1->id, $course1->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course2->id, $studentrole->id);

        (new \completion_completion(['userid' => $user1->id, 'course' => $course1->id]))->mark_complete($now);
        (new \completion_completion(['userid' => $user2->id, 'course' => $course2->id]))->mark_complete($now);

        $conditions = [
            $this->make_course_completed_condition($rule, $course1->id),
            $this->make_course_completed_condition($rule, $course2->id),
            $this->make_course_completed_condition($rule, $course3->id),
        ];

        $sql = condition_manager::build_sql_data($conditions, rule_manager::CONDITIONS_OPERATOR_OR);

        // Merged path: no JOINs, a single EXISTS in the WHERE.
        $this->assertEmpty($sql->get_join(), 'Merged OR conditions should produce no JOINs');
        $this->assertStringContainsStringIgnoringCase('EXISTS', $sql->get_where());

        // Correctness: user1 and user2 match (any completion), user3 does not.
        $basesql = "SELECT DISTINCT u.id FROM {user} u {$sql->get_join()} WHERE {$sql->get_where()}";
        $rows = $DB->get_records_sql($basesql, $sql->get_params());
        $this->assertArrayHasKey($user1->id, $rows);
        $this->assertArrayHasKey($user2->id, $rows);
        $this->assertArrayNotHasKey($user3->id, $rows);
    }

    /**
     * Multiple course_completed conditions under AND are NOT merged — each requires its
     * own JOIN to enforce that the user completed every listed course.
     */
    public function test_build_sql_data_does_not_merge_course_completed_under_and(): void {
        global $DB;

        $this->resetAfterTest();

        $now = time();
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $cohort = $this->getDataGenerator()->create_cohort();

        $rule = new rule(0, (object)[
            'name'     => 'No-merge AND rule',
            'cohortid' => $cohort->id,
            'operator' => rule_manager::CONDITIONS_OPERATOR_AND,
        ]);
        $rule->save();

        $course1 = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $course2 = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        // user1 completed both courses, user2 completed only course1.
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($user1->id, $course1->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($user1->id, $course2->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course1->id, $studentrole->id);

        (new \completion_completion(['userid' => $user1->id, 'course' => $course1->id]))->mark_complete($now);
        (new \completion_completion(['userid' => $user1->id, 'course' => $course2->id]))->mark_complete($now);
        (new \completion_completion(['userid' => $user2->id, 'course' => $course1->id]))->mark_complete($now);

        $conditions = [
            $this->make_course_completed_condition($rule, $course1->id),
            $this->make_course_completed_condition($rule, $course2->id),
        ];

        $sql = condition_manager::build_sql_data($conditions, rule_manager::CONDITIONS_OPERATOR_AND);

        // AND path: separate JOINs retained, no EXISTS.
        $this->assertNotEmpty($sql->get_join(), 'AND conditions should still use JOINs');
        $this->assertStringNotContainsStringIgnoringCase('EXISTS', $sql->get_where());

        // Correctness: only user1 completed both courses.
        $basesql = "SELECT DISTINCT u.id FROM {user} u {$sql->get_join()} WHERE {$sql->get_where()}";
        $rows = $DB->get_records_sql($basesql, $sql->get_params());
        $this->assertArrayHasKey($user1->id, $rows);
        $this->assertArrayNotHasKey($user2->id, $rows);
    }

    /**
     * A single course_completed condition under OR is not merged (no benefit) and falls
     * back to the regular JOIN path.
     */
    public function test_build_sql_data_singleton_course_completed_uses_join(): void {
        $this->resetAfterTest();

        $cohort = $this->getDataGenerator()->create_cohort();
        $rule = new rule(0, (object)[
            'name'     => 'Singleton OR rule',
            'cohortid' => $cohort->id,
            'operator' => rule_manager::CONDITIONS_OPERATOR_OR,
        ]);
        $rule->save();

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $conditions = [$this->make_course_completed_condition($rule, $course->id)];

        $sql = condition_manager::build_sql_data($conditions, rule_manager::CONDITIONS_OPERATOR_OR);

        // Singleton falls back to regular get_sql() which uses a JOIN, not EXISTS.
        $this->assertNotEmpty($sql->get_join());
        $this->assertStringNotContainsStringIgnoringCase('EXISTS', $sql->get_where());
    }

    /**
     * Date-bounded (OPERATOR_BEFORE/AFTER) course_completed conditions are not merged
     * even under OR — they stay as individual JOINs.
     */
    public function test_build_sql_data_does_not_merge_date_bounded_conditions(): void {
        $this->resetAfterTest();

        $cohort = $this->getDataGenerator()->create_cohort();
        $rule = new rule(0, (object)[
            'name'     => 'Date-bounded OR rule',
            'cohortid' => $cohort->id,
            'operator' => rule_manager::CONDITIONS_OPERATOR_OR,
        ]);
        $rule->save();

        $course1 = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $course2 = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        $conditions = [
            $this->make_course_completed_condition($rule, $course1->id, course_completed::OPERATOR_BEFORE, time()),
            $this->make_course_completed_condition($rule, $course2->id, course_completed::OPERATOR_AFTER, time()),
        ];

        $sql = condition_manager::build_sql_data($conditions, rule_manager::CONDITIONS_OPERATOR_OR);

        // Date-bounded conditions cannot be merged — each uses its own JOIN.
        $this->assertNotEmpty($sql->get_join());
        $this->assertStringNotContainsStringIgnoringCase('EXISTS', $sql->get_where());
        $this->assertStringContainsString('OR', $sql->get_where());
    }

    /**
     * Mixed OPERATOR_ANY (mergeable) and OPERATOR_BEFORE (not mergeable) conditions
     * under OR: the ANY group is merged into EXISTS; the BEFORE stays as a JOIN.
     */
    public function test_build_sql_data_mixed_mergeable_and_date_bounded(): void {
        $this->resetAfterTest();

        $cohort = $this->getDataGenerator()->create_cohort();
        $rule = new rule(0, (object)[
            'name'     => 'Mixed OR rule',
            'cohortid' => $cohort->id,
            'operator' => rule_manager::CONDITIONS_OPERATOR_OR,
        ]);
        $rule->save();

        $course1 = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $course2 = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $course3 = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        $conditions = [
            $this->make_course_completed_condition($rule, $course1->id),
            $this->make_course_completed_condition($rule, $course2->id),
            $this->make_course_completed_condition($rule, $course3->id, course_completed::OPERATOR_BEFORE, time()),
        ];

        $sql = condition_manager::build_sql_data($conditions, rule_manager::CONDITIONS_OPERATOR_OR);

        // The two OPERATOR_ANY conditions are merged (EXISTS); OPERATOR_BEFORE keeps its JOIN.
        $this->assertStringContainsStringIgnoringCase('EXISTS', $sql->get_where());
        $this->assertNotEmpty($sql->get_join(), 'OPERATOR_BEFORE condition should still produce a JOIN');
    }

    /**
     * Helper to create and save a course_not_completed condition record.
     *
     * @param rule $rule
     * @param int $courseid
     * @return condition
     */
    protected function make_course_not_completed_condition(rule $rule, int $courseid): condition {
        $instance = course_not_completed::get_instance(0, (object)['ruleid' => $rule->get('id'), 'sortorder' => 1]);
        $instance->set_config_data(['courseid' => $courseid, 'timecompleted' => 0]);
        $instance->get_record()->save();
        return $instance->get_record();
    }

    /**
     * Multiple course_not_completed conditions under OR must not be merged.
     *
     * course_not_completed uses NOT-completed semantics (LEFT JOIN / IS NULL); merging
     * them into a single EXISTS ... IN would invert the logic and return wrong results.
     * This test verifies the SQL is correct and that build_sql_data() does not crash.
     */
    public function test_build_sql_data_does_not_merge_course_not_completed_under_or(): void {
        global $DB;

        $this->resetAfterTest();

        $now = time();
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $cohort = $this->getDataGenerator()->create_cohort();

        $rule = new rule(0, (object)[
            'name'     => 'Not-completed OR rule',
            'cohortid' => $cohort->id,
            'operator' => rule_manager::CONDITIONS_OPERATOR_OR,
        ]);
        $rule->save();

        $course1 = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $course2 = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        // user1 completed course1 (so has NOT completed course2).
        // user2 completed neither course.
        // user3 completed both courses.
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();

        foreach ([$user1, $user3] as $user) {
            $this->getDataGenerator()->enrol_user($user->id, $course1->id, $studentrole->id);
        }
        foreach ([$user1, $user2, $user3] as $user) {
            $this->getDataGenerator()->enrol_user($user->id, $course2->id, $studentrole->id);
        }

        (new \completion_completion(['userid' => $user1->id, 'course' => $course1->id]))->mark_complete($now);
        (new \completion_completion(['userid' => $user3->id, 'course' => $course1->id]))->mark_complete($now);
        (new \completion_completion(['userid' => $user3->id, 'course' => $course2->id]))->mark_complete($now);

        $conditions = [
            $this->make_course_not_completed_condition($rule, $course1->id),
            $this->make_course_not_completed_condition($rule, $course2->id),
        ];

        // Must not crash (previously would throw coding_exception via incorrect merge).
        $sql = condition_manager::build_sql_data($conditions, rule_manager::CONDITIONS_OPERATOR_OR);

        // Should not use EXISTS — course_not_completed relies on LEFT JOIN / IS NULL.
        $this->assertStringNotContainsStringIgnoringCase('EXISTS', $sql->get_where());

        // Correctness: user1 has not completed course2, user2 has not completed either
        // course, user3 has completed both so should be excluded.
        $basesql = "SELECT DISTINCT u.id FROM {user} u {$sql->get_join()} WHERE {$sql->get_where()}";
        $rows = $DB->get_records_sql($basesql, $sql->get_params());
        $this->assertArrayHasKey($user1->id, $rows, 'user1 has not completed course2 so should match');
        $this->assertArrayHasKey($user2->id, $rows, 'user2 has not completed either course so should match');
        $this->assertArrayNotHasKey($user3->id, $rows, 'user3 completed both courses so should not match');
    }
}
