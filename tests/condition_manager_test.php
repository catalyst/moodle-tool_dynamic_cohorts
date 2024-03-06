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

use tool_dynamic_cohorts\event\condition_created;
use tool_dynamic_cohorts\event\condition_deleted;
use tool_dynamic_cohorts\event\condition_updated;

/**
 * Tests for condition manager class.
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_dynamic_cohorts\condition_manager
 */
class condition_manager_test extends \advanced_testcase {

    /**
     * Test all conditions.
     */
    public function test_get_all_conditions() {
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
    public function test_process_form() {
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
    public function test_delete_conditions() {
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
}
