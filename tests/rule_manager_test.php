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

use moodle_url;
use moodle_exception;
use tool_dynamic_cohorts\event\rule_created;
use tool_dynamic_cohorts\event\rule_deleted;
use tool_dynamic_cohorts\event\rule_updated;
use tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\user_profile;

/**
 * Tests for rule manager class.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers     \tool_dynamic_cohorts\rule_manager
 */
class rule_manager_test extends \advanced_testcase {

    /**
     * Get condition instance for testing.
     *
     * @param string $classname Class name.
     * @param array $configdata Config data to be set.
     * @return condition_base
     */
    protected function get_condition(string $classname, array $configdata = []): condition_base {
        $condition = condition_base::get_instance(0, (object)['classname' => $classname]);
        $condition->set_config_data($configdata);

        return $condition;
    }

    /**
     * Test building edit URL.
     */
    public function test_build_edit_url() {
        $this->resetAfterTest();

        $data = ['name' => 'Test', 'enabled' => 1, 'cohortid' => 2, 'description' => ''];
        $rule = new rule(0, (object)$data);
        $rule->save();

        $actual = rule_manager::build_edit_url($rule);
        $expected = new moodle_url('/admin/tool/dynamic_cohorts/edit.php', ['ruleid' => $rule->get('id')]);
        $this->assertEquals($expected->out(), $actual->out());
    }

    /**
     * Test delete URL.
     */
    public function test_build_rule_delete_url() {
        $this->resetAfterTest();

        $data = ['name' => 'Test', 'enabled' => 1, 'cohortid' => 2, 'description' => ''];
        $rule = new rule(0, (object)$data);
        $rule->save();

        $actual = rule_manager::build_delete_url($rule);
        $expected = new moodle_url('/admin/tool/dynamic_cohorts/delete.php', [
            'ruleid' => $rule->get('id'),
            'sesskey' => sesskey(),
        ]);

        $this->assertEquals($expected->out(), $actual->out());
    }

    /**
     * Test building rule data for form.
     */
    public function test_build_rule_data_for_form() {
        $this->resetAfterTest();

        $rule = new rule(0, (object)['name' => 'Test rule', 'cohortid' => 0, 'description' => 'Test description']);
        $instance = $this->get_condition(
            'tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\user_profile',
            [
                'profilefield' => 'username',
                'username_operator' => user_profile::TEXT_IS_EQUAL_TO,
                'username_value' => 'user1',
            ]
        );

        $instance->get_record()->set('ruleid', $rule->get('id'));
        $instance->get_record()->set('sortorder', 0);
        $instance->get_record()->save();

        $condition = condition::get_record(['id' => $instance->get_record()->get('id')]);
        $conditions[] = (array) $condition->to_record() +
            ['description' => $instance->get_config_description()] +
            ['name' => $instance->get_name()];

        $expected = [
            'name' => 'Test rule',
            'description' => 'Test description',
            'cohortid' => 0,
            'enabled' => 0,
            'bulkprocessing' => 0,
            'broken' => 0,
            'id' => 0,
            'timecreated' => 0,
            'timemodified' => 0,
            'usermodified' => 0,
            'conditionjson' => json_encode($conditions),
        ];

        $this->assertSame($expected, rule_manager::build_data_for_form($rule));
    }

    /**
     * Data provider for testing test_process_rule_form_with_invalid_data.
     *
     * @return array
     */
    public function process_rule_form_with_invalid_data_provider(): array {
        return [
            [[]],
            [['name' => 'Test']],
            [['enabled' => 1]],
            [['cohortid' => 1]],
            [['description' => '']],
            [['conditionjson' => '']],
            [['enabled' => 1, 'cohortid' => 1, 'description' => '', 'conditionjson' => '']],
            [['name' => 'Test', 'cohortid' => 1, 'description' => '', 'conditionjson' => '']],
            [['name' => 'Test', 'enabled' => 1, 'description' => '', 'conditionjson' => '']],
            [['name' => 'Test', 'enabled' => 1, 'cohortid' => 1, 'conditionjson' => '']],
            [['name' => 'Test', 'enabled' => 1, 'cohortid' => 1, 'description' => '']],
        ];
    }

    /**
     * Test processing rules with invalid data.
     *
     * @dataProvider process_rule_form_with_invalid_data_provider
     * @param array $formdata Broken form data
     */
    public function test_process_rule_form_with_invalid_data(array $formdata) {
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('Invalid rule data');

        rule_manager::process_form((object)$formdata);
    }

    /**
     * Test new rules are created when processing form data.
     */
    public function test_process_rule_form_new_rule() {
        global $DB;

        $this->resetAfterTest();
        $this->assertEquals(0, $DB->count_records(rule::TABLE));

        $cohort1 = $this->getDataGenerator()->create_cohort();
        $cohort2 = $this->getDataGenerator()->create_cohort();
        $cohort3 = $this->getDataGenerator()->create_cohort();

        $formdata = ['name' => 'Test', 'cohortid' => $cohort1->id, 'description' => '',
            'conditionjson' => '', 'bulkprocessing' => 1];

        $rule = rule_manager::process_form((object)$formdata);
        $this->assertEquals(1, $DB->count_records(rule::TABLE));

        $rule = rule::get_record(['id' => $rule->get('id')]);
        unset($formdata['conditionjson']);
        foreach ($formdata as $field => $value) {
            $this->assertEquals($value, $rule->get($field));
        }

        $formdata = ['name' => 'Test', 'cohortid' => $cohort2->id, 'description' => '',
            'conditionjson' => '', 'bulkprocessing' => 1];
        $rule = rule_manager::process_form((object)$formdata);
        $this->assertEquals(2, $DB->count_records(rule::TABLE));

        $rule = rule::get_record(['id' => $rule->get('id')]);
        unset($formdata['conditionjson']);
        foreach ($formdata as $field => $value) {
            $this->assertEquals($value, $rule->get($field));
        }

        $cohort = $this->getDataGenerator()->create_cohort();
        $formdata = ['name' => 'Test1', 'cohortid' => $cohort3->id, 'description' => '',
            'conditionjson' => '', 'bulkprocessing' => 1];
        $rule = rule_manager::process_form((object)$formdata);
        $this->assertEquals(3, $DB->count_records(rule::TABLE));

        $rule = rule::get_record(['id' => $rule->get('id')]);
        unset($formdata['conditionjson']);
        foreach ($formdata as $field => $value) {
            $this->assertEquals($value, $rule->get($field));
        }
    }

    /**
     * Test existing rules are updated when processing form data.
     */
    public function test_process_rule_form_existing_rule() {
        global $DB;

        $this->resetAfterTest();
        $this->assertEquals(0, $DB->count_records(rule::TABLE));

        $cohort = $this->getDataGenerator()->create_cohort();
        $formdata = ['name' => 'Test', 'cohortid' => $cohort->id, 'description' => ''];
        $rule = new rule(0, (object)$formdata);
        $rule->create();

        $this->assertEquals(1, $DB->count_records(rule::TABLE));
        unset($formdata['conditionjson']);
        foreach ($formdata as $field => $value) {
            $this->assertEquals($value, $rule->get($field));
        }

        $cohort = $this->getDataGenerator()->create_cohort();
        $formdata = ['id' => $rule->get('id'), 'name' => 'Test1', 'cohortid' => $cohort->id,
            'description' => 'D', 'conditionjson' => '', 'bulkprocessing' => 1];
        $rule = rule_manager::process_form((object)$formdata);
        $this->assertEquals(1, $DB->count_records(rule::TABLE));

        $rule = rule::get_record(['id' => $rule->get('id')]);
        unset($formdata['conditionjson']);
        foreach ($formdata as $field => $value) {
            $this->assertEquals($value, $rule->get($field));
        }
    }

    /**
     * Test trying to submit form data and sending not existing cohort.
     */
    public function test_process_rule_form_with_not_existing_cohort() {
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('Invalid rule data. Cohort is invalid: 999');

        $formdata = ['name' => 'Test', 'cohortid' => 999, 'description' => '', 'conditionjson' => '', 'bulkprocessing' => 1];
        rule_manager::process_form((object)$formdata);
    }

    /**
     * Test trying to submit form data and sending a cohort taken by other component.
     */
    public function test_process_rule_form_with_cohort_managed_by_other_component() {
        $this->resetAfterTest();

        $cohort = $this->getDataGenerator()->create_cohort(['component' => 'mod_assign']);
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('Invalid rule data. Cohort is invalid: ' . $cohort->id);

        $formdata = ['name' => 'Test', 'cohortid' => $cohort->id, 'description' => '',
            'conditionjson' => '', 'bulkprocessing' => 1];
        rule_manager::process_form((object)$formdata);
    }

    /**
     * Test trying to submit form data and sending a cohort taken by other rule.
     */
    public function test_process_rule_form_with_cohort_managed_by_another_rule() {
        global $DB;

        $this->resetAfterTest();

        $cohort = $this->getDataGenerator()->create_cohort(['component' => 'tool_dynamic_cohorts']);
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('Cohort ' . $cohort->id . ' is already managed by tool_dynamic_cohorts');

        $formdata = ['name' => 'Test1', 'cohortid' => $cohort->id, 'description' => 'D', 'conditionjson' => '',
            'bulkprocessing' => 1];
        rule_manager::process_form((object)$formdata);
        $this->assertEquals('tool_dynamic_cohorts', $DB->get_field('cohort', 'component', ['id' => $cohort->id]));

        // Trying to make a new rule with a cohort that is already taken. Should throw exception.
        $formdata = ['name' => 'Test2', 'cohortid' => $cohort->id, 'description' => 'D',
            'conditionjson' => '', 'bulkprocessing' => 1];
        rule_manager::process_form((object)$formdata);
    }

    /**
     * Test submitting form data keeps cohort.
     */
    public function test_process_rule_form_update_rule_form_keeping_cohort() {
        global $DB;

        $this->resetAfterTest();

        $cohort = $this->getDataGenerator()->create_cohort();

        $formdata = ['name' => 'Test1', 'cohortid' => $cohort->id, 'description' => 'D',
            'conditionjson' => '', 'bulkprocessing' => 1];
        $rule = rule_manager::process_form((object)$formdata);
        $this->assertEquals('tool_dynamic_cohorts', $DB->get_field('cohort', 'component', ['id' => $cohort->id]));

        // Update the rule, changing the name. Should work as cohort is the same.
        $formdata = ['id' => $rule->get('id'), 'name' => 'Test1',
            'cohortid' => $cohort->id, 'description' => 'D', 'conditionjson' => '', 'bulkprocessing' => 1];
        rule_manager::process_form((object)$formdata);

        $this->assertEquals('tool_dynamic_cohorts', $DB->get_field('cohort', 'component', ['id' => $cohort->id]));
    }

    /**
     * Test triggering events.
     */
    public function test_process_rule_form_triggers_events() {
        $this->resetAfterTest();

        $cohort = $this->getDataGenerator()->create_cohort();

        $eventsink = $this->redirectEvents();
        $formdata = ['name' => 'Test1', 'cohortid' => $cohort->id, 'description' => 'D',
            'conditionjson' => '', 'bulkprocessing' => 1];
        $rule = rule_manager::process_form((object) $formdata);

        $events = array_filter($eventsink->get_events(), function ($event) {
            return $event instanceof rule_created;
        });

        $this->assertCount(1, $events);
        $this->assertEquals($rule->get('id'), reset($events)->other['ruleid']);
        $eventsink->clear();

        // Update the rule, changing the name. Should work as cohort is the same.
        $formdata = ['id' => $rule->get('id'), 'name' => 'Test1',
            'cohortid' => $cohort->id, 'description' => 'D', 'conditionjson' => '', 'bulkprocessing' => 1];
        rule_manager::process_form((object) $formdata);

        $events = array_filter($eventsink->get_events(), function ($event) {
            return $event instanceof rule_updated;
        });

        $this->assertCount(1, $events);
        $this->assertEquals($rule->get('id'), reset($events)->other['ruleid']);
        $eventsink->clear();
    }

    /**
     * Test trying to submit form data and sending a cohort taken by other component.
     */
    public function test_process_rule_form_without_condition_data() {
        $this->resetAfterTest();

        $cohort = $this->getDataGenerator()->create_cohort(['component' => 'tool_dynamic_cohorts']);
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('Invalid rule data. Missing condition data.');

        $formdata = ['name' => 'Test', 'cohortid' => $cohort->id, 'description' => '', 'bulkprocessing' => 1];
        rule_manager::process_form((object)$formdata);
    }

    /**
     * Test conditions created when processing rule form data.
     */
    public function test_process_rule_form_with_conditions() {
        global $DB;

        $this->resetAfterTest();
        $cohort = $this->getDataGenerator()->create_cohort();

        $this->assertEquals(0, $DB->count_records(rule::TABLE));

        // Creating rule without conditions.
        $formdata = ['name' => 'Test', 'cohortid' => $cohort->id, 'description' => '',
            'conditionjson' => '', 'bulkprocessing' => 1];
        $rule = rule_manager::process_form((object)$formdata);

        // No conditions yet. Rule should be ok.
        $this->assertFalse($rule->is_broken());
        // Rules disabled by default.
        $this->assertFalse($rule->is_enabled());

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
        $rule = rule_manager::process_form((object)$formdata);
        $this->assertEquals(1, $DB->count_records(rule::TABLE));
        $this->assertCount(0, $rule->get_condition_records());

        // No conditions yet. Rule should be ok.
        $this->assertFalse($rule->is_broken());
        // Rules disabled by default.
        $this->assertFalse($rule->is_enabled());

        // Updating the rule with 3 new conditions. Expecting 3 new conditions to be created.
        $formdata = ['id' => $rule->get('id'), 'name' => 'Test', 'enabled' => 1, 'cohortid' => $cohort->id,
            'description' => '', 'conditionjson' => $conditionjson, 'isconditionschanged' => true, 'bulkprocessing' => 1];
        $rule = rule_manager::process_form((object)$formdata);
        $this->assertEquals(1, $DB->count_records(rule::TABLE));
        $this->assertCount(3, $rule->get_condition_records());

        // Rule should be broken as all conditions are broken (not existing class).
        $this->assertTrue($rule->is_broken());
        $this->assertFalse($rule->is_enabled());

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
        $rule = rule_manager::process_form((object)$formdata);
        $this->assertEquals(1, $DB->count_records(rule::TABLE));
        $this->assertCount(3, $rule->get_condition_records());
        $this->assertTrue($rule->is_broken());
        $this->assertFalse($rule->is_enabled());

        $this->assertTrue(condition::record_exists_select('classname = ? AND ruleid = ?', ['class10', $rule->get('id')]));
        $this->assertFalse(condition::record_exists_select('classname = ? AND ruleid = ?', ['class2', $rule->get('id')]));
        $this->assertTrue(condition::record_exists_select('classname = ? AND ruleid = ?', ['class32', $rule->get('id')]));
        $this->assertTrue(condition::record_exists_select('classname = ? AND ruleid = ?', ['class4', $rule->get('id')]));

        $formdata = ['id' => $rule->get('id'), 'name' => 'Test', 'enabled' => 1, 'cohortid' => $cohort->id,
            'description' => '', 'conditionjson' => '', 'isconditionschanged' => true, 'bulkprocessing' => 1];
        $rule = rule_manager::process_form((object)$formdata);
        $this->assertEquals(1, $DB->count_records(rule::TABLE));
        $this->assertCount(0, $rule->get_condition_records());

        // Should be unbroken as all broken conditions are gone.
        $this->assertFalse($rule->is_broken());
        // Rules are disabled by default.
        $this->assertFalse($rule->is_enabled());
    }

    /**
     * Test rule deleting clear all related tables.
     */
    public function test_deleting_rule_deletes_all_related_records() {
        global $DB;

        $this->resetAfterTest();

        $this->assertSame(0, $DB->count_records(rule::TABLE));
        $this->assertSame(0, $DB->count_records(condition::TABLE));

        $cohort = $this->getDataGenerator()->create_cohort();

        $rule = new rule(0, (object)['name' => 'Test rule', 'cohortid' => $cohort->id]);
        $rule->save();

        $condition = new condition(0, (object)['ruleid' => $rule->get('id'), 'classname' => 'test', 'sortorder' => 0]);
        $condition->save();

        $this->assertSame(1, $DB->count_records(rule::TABLE));
        $this->assertSame(1, $DB->count_records(condition::TABLE));

        rule_manager::delete_rule($rule);
        $this->assertSame(0, $DB->count_records(rule::TABLE));
        $this->assertSame(0, $DB->count_records(condition::TABLE));
    }

    /**
     * Test cohorts are getting released after related rules are deleted.
     */
    public function test_deleting_rule_releases_cohorts() {
        global $DB;

        $this->resetAfterTest();

        $cohort1 = $this->getDataGenerator()->create_cohort();
        $cohort2 = $this->getDataGenerator()->create_cohort();
        $this->assertEquals('', $DB->get_field('cohort', 'component', ['id' => $cohort1->id]));
        $this->assertEquals('', $DB->get_field('cohort', 'component', ['id' => $cohort2->id]));

        $rule1 = new rule(0, (object)['name' => 'Test rule', 'cohortid' => $cohort1->id]);
        $rule1->save();
        cohort_manager::manage_cohort($cohort1->id);

        $this->assertEquals('tool_dynamic_cohorts', $DB->get_field('cohort', 'component', ['id' => $cohort1->id]));

        $rule2 = new rule(0, (object)['name' => 'Test rule 2', 'cohortid' => $cohort2->id]);
        $rule2->save();
        cohort_manager::manage_cohort($cohort2->id);
        $this->assertEquals('tool_dynamic_cohorts', $DB->get_field('cohort', 'component', ['id' => $cohort1->id]));

        rule_manager::delete_rule($rule1);
        $this->assertEquals('tool_dynamic_cohorts', $DB->get_field('cohort', 'component', ['id' => $cohort2->id]));

        rule_manager::delete_rule($rule2);
        $this->assertEquals('', $DB->get_field('cohort', 'component', ['id' => $cohort2->id]));
    }

    /**
     * Test deleting a rule triggers event.
     */
    public function test_deleting_rule_triggers_event() {
        $this->resetAfterTest();

        $cohort = $this->getDataGenerator()->create_cohort(['component' => 'tool_dynamic_cohorts']);

        $rule = new rule(0, (object)['name' => 'Test rule', 'cohortid' => $cohort->id]);
        $rule->save();
        $expectedruleid = $rule->get('id');

        $eventsink = $this->redirectEvents();

        rule_manager::delete_rule($rule);

        $events = array_filter($eventsink->get_events(), function ($event) {
            return $event instanceof rule_deleted;
        });

        $this->assertCount(1, $events);
        $this->assertEquals($expectedruleid, reset($events)->other['ruleid']);
    }
}