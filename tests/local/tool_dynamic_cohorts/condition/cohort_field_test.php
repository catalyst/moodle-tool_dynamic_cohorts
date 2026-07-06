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
 * Unit tests for cohort field condition class.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers     \tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\cohort_field
 */
final class cohort_field_test extends \advanced_testcase {
    /**
     * Get condition instance for testing.
     *
     * @param array $configdata Config data to be set.
     * @return condition_base
     */
    protected function get_condition(array $configdata = []): condition_base {
        $condition = condition_base::get_instance(0, (object)[
            'classname' => '\tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\cohort_field',
        ]);
        $condition->set_config_data($configdata);

        return $condition;
    }

    /**
     * Test retrieving of config data.
     */
    public function test_retrieving_configdata(): void {
        $formdata = (object)[
            'id' => 1,
            'cohort_field_operator' => 2,
            'cohort_field_field' => 'visible',
            'visible_operator' => 3,
            'visible_value' => 0,
        ];

        $actual = $this->get_condition()::retrieve_config_data($formdata);
        $expected = [
            'cohort_field_field' => 'visible',
            'cohort_field_operator' => 2,
            'visible_operator' => 3,
            'visible_value' => 0,
        ];
        $this->assertEquals($expected, $actual);
    }

    /**
     * Test setting and getting config data.
     */
    public function test_set_and_get_configdata(): void {
        $instance = $this->get_condition([
            'cohort_field_operator' => 2,
            'cohort_field_field' => 'visible',
            'visible_operator' => 3,
            'visible_value' => 0,
        ]);

        $this->assertEquals(
            ['cohort_field_field' => 'visible', 'cohort_field_operator' => 2, 'visible_operator' => 3, 'visible_value' => 0],
            $instance->get_config_data()
        );
    }

    /**
     * Data provider for testing config description.
     *
     * @return array[]
     */
    public static function config_description_data_provider(): array {
        return [
            [condition_base::TEXT_CONTAINS, 'Users who are not members of cohorts with field \'Theme\' contains 123'],
            [
                condition_base::TEXT_DOES_NOT_CONTAIN,
                'Users who are not members of cohorts with field \'Theme\' doesn\'t contain 123',
            ],
            [condition_base::TEXT_IS_EQUAL_TO, 'Users who are not members of cohorts with field \'Theme\' is equal to 123'],
            [condition_base::TEXT_IS_NOT_EQUAL_TO, 'Users who are not members of cohorts with field \'Theme\' isn\'t equal to 123'],
            [condition_base::TEXT_STARTS_WITH, 'Users who are not members of cohorts with field \'Theme\' starts with 123'],
            [condition_base::TEXT_ENDS_WITH, 'Users who are not members of cohorts with field \'Theme\' ends with 123'],
            [condition_base::TEXT_IS_EMPTY, 'Users who are not members of cohorts with field \'Theme\' is empty '],
            [condition_base::TEXT_IS_NOT_EMPTY, 'Users who are not members of cohorts with field \'Theme\' is not empty '],
        ];
    }

    /**
     * Test getting config description.
     *
     * @dataProvider config_description_data_provider
     * @param int $operator
     * @param string $expected
     */
    public function test_config_description(int $operator, string $expected): void {
        $condition = $this->get_condition([
            'cohort_field_operator' => cohort_field::OPERATOR_IS_NOT_MEMBER_OF,
            'cohort_field_field' => 'theme',
            'theme_operator' => $operator,
            'theme_value' => '123',
        ]);

        $this->assertSame($expected, $condition->get_config_description());
    }

    /**
     * Test getting config description.
     */
    public function test_config_description_context_id(): void {
        $this->resetAfterTest();

        $this->setAdminUser();

        $coursecategory = $this->getDataGenerator()->create_category();
        $catcontext = \context_coursecat::instance($coursecategory->id);

        $condition = $this->get_condition([
            'cohort_field_operator' => cohort_field::OPERATOR_IS_MEMBER_OF,
            'cohort_field_field' => 'contextid',
            'contextid_operator' => condition_base::TEXT_IS_EQUAL_TO,
            'contextid_value' => $catcontext->id,
        ]);

        $this->assertSame(
            'Users who are members of cohorts with field \'Context\' is equal to ' . $coursecategory->name,
            $condition->get_config_description()
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
    public function test_is_broken(): void {
        $condition = $this->get_condition();

        // Not configured should be always valid.
        $this->assertFalse($condition->is_broken());

        // Context is not exist/gone.
        $condition = $this->get_condition([
            'cohort_field_operator' => cohort_field::OPERATOR_IS_MEMBER_OF,
            'cohort_field_field' => 'contextid',
            'contextid_operator' => condition_base::TEXT_IS_EQUAL_TO,
            'contextid_value' => 7777,
        ]);
        $this->assertTrue($condition->is_broken());

        $condition = $this->get_condition([
            'cohort_field_operator' => cohort_field::OPERATOR_IS_MEMBER_OF,
            'cohort_field_field' => 'name',
            'name_operator' => condition_base::TEXT_IS_EMPTY,
            'name_value' => '',
        ]);
        $this->assertFalse($condition->is_broken());

        $condition = $this->get_condition([
            'cohort_field_operator' => cohort_field::OPERATOR_IS_MEMBER_OF,
            'cohort_field_field' => 'name',
            'name_operator' => condition_base::TEXT_IS_NOT_EMPTY,
            'name_value' => '',
        ]);
        $this->assertFalse($condition->is_broken());

        $condition = $this->get_condition([
            'cohort_field_operator' => cohort_field::OPERATOR_IS_MEMBER_OF,
            'cohort_field_field' => 'name',
            'name_operator' => condition_base::TEXT_IS_EQUAL_TO,
            'name_value' => '',
        ]);
        $this->assertTrue($condition->is_broken());

        $condition = $this->get_condition([
            'cohort_field_operator' => cohort_field::OPERATOR_IS_MEMBER_OF,
            'cohort_field_field' => 'notexists',
            'notexists_operator' => condition_base::TEXT_IS_EQUAL_TO,
            'notexists_value' => '',
        ]);
        $this->assertTrue($condition->is_broken());
    }

    /**
     * Test getting SQL.
     */
    public function test_get_sql_data_standard_fields(): void {
        global $DB;

        $this->resetAfterTest();

        $cohort1 = $this->getDataGenerator()->create_cohort();
        $cohort2 = $this->getDataGenerator()->create_cohort();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        cohort_add_member($cohort1->id, $user1->id);
        cohort_add_member($cohort1->id, $user2->id);

        $totalusers = $DB->count_records('user');

        $condition = $this->get_condition([
            'cohort_field_operator' => cohort_field::OPERATOR_IS_MEMBER_OF,
            'cohort_field_field' => 'name',
            'name_operator' => cohort_field::TEXT_IS_EQUAL_TO,
            'name_value' => $cohort1->name,
        ]);

        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        // User 1 and user 2 as they are members of cohort 1.
        $this->assertCount(2, $DB->get_records_sql($sql, $result->get_params()));

        $condition = $this->get_condition([
            'cohort_field_operator' => cohort_field::OPERATOR_IS_NOT_MEMBER_OF,
            'cohort_field_field' => 'name',
            'name_operator' => cohort_field::TEXT_IS_EQUAL_TO,
            'name_value' => $cohort1->name,
        ]);

        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        // Everyone except user 1 and user 2 as they are member of cohort 1.
        $this->assertCount($totalusers - 2, $DB->get_records_sql($sql, $result->get_params()));

        $condition = $this->get_condition([
            'cohort_field_operator' => cohort_field::OPERATOR_IS_NOT_MEMBER_OF,
            'cohort_field_field' => 'name',
            'name_operator' => cohort_field::TEXT_STARTS_WITH,
            'name_value' => $cohort2->name,
        ]);

        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        // Everyone as cohort is empty.
        $this->assertCount($totalusers, $DB->get_records_sql($sql, $result->get_params()));
    }

    /**
     * Create Cohort custom field for testing.
     *
     * @return \core_customfield\field_controller
     */

    /**
     * Create cohort custom field for testing.
     *
     * @param string $shortname Field shortname
     * @param string $datatype $field data type.
     *
     * @return \core_customfield\field_controller
     */
    protected function create_cohort_custom_field(
        string $shortname = 'testfield1',
        string $datatype = 'text'
    ): \core_customfield\field_controller {
        $fieldcategory = self::getDataGenerator()->create_custom_field_category([
            'component' => 'core_cohort',
            'area' => 'cohort',
            'name' => 'Other fields',
        ]);

        return self::getDataGenerator()->create_custom_field([
            'shortname' => $shortname,
            'name' => 'Custom field',
            'type' => $datatype,
            'categoryid' => $fieldcategory->get('id'),
        ]);
    }

    /**
     * Test getting config description when using a custom field.
     */
    public function test_config_description_custom_field(): void {
        if (!class_exists(\core_cohort\customfield\cohort_handler::class)) {
            $this->markTestSkipped();
        }

        $this->resetAfterTest();
        $this->create_cohort_custom_field();

        $condition = $this->get_condition([
            'cohort_field_operator' => cohort_field::OPERATOR_IS_MEMBER_OF,
            'cohort_field_field' => cohort_field::CUSTOM_FIELD_PREFIX . 'testfield1',
            cohort_field::CUSTOM_FIELD_PREFIX . 'testfield1_operator' => condition_base::TEXT_IS_EQUAL_TO,
            cohort_field::CUSTOM_FIELD_PREFIX . 'testfield1_value' => 'Test value',
        ]);

        $this->assertSame(
            'Users who are members of cohorts with field \'Custom field\' is equal to Test value',
            $condition->get_config_description()
        );

        $checkboxfield = $this->create_cohort_custom_field('checkboxfield', 'checkbox');
        $checkboxfieldname = cohort_field::CUSTOM_FIELD_PREFIX . $checkboxfield->get('shortname');
        $condition = $this->get_condition([
            'cohort_field_operator' => cohort_field::OPERATOR_IS_MEMBER_OF,
            'cohort_field_field' => $checkboxfieldname,
            $checkboxfieldname . '_operator' => condition_base::TEXT_IS_EQUAL_TO,
            $checkboxfieldname . '_value' => 1,
        ]);

        $this->assertSame(
            'Users who are members of cohorts with field \'Custom field\' is equal to Yes',
            $condition->get_config_description()
        );

        $condition = $this->get_condition([
            'cohort_field_operator' => cohort_field::OPERATOR_IS_MEMBER_OF,
            'cohort_field_field' => $checkboxfieldname,
            $checkboxfieldname . '_operator' => condition_base::TEXT_IS_EQUAL_TO,
            $checkboxfieldname . '_value' => 0,
        ]);

        $this->assertSame(
            'Users who are members of cohorts with field \'Custom field\' is equal to No',
            $condition->get_config_description()
        );

        $now = time();
        $datefield = $this->create_cohort_custom_field('datefield', 'date');
        $datefieldfieldname = cohort_field::CUSTOM_FIELD_PREFIX . $datefield->get('shortname');
        $condition = $this->get_condition([
            'cohort_field_operator' => cohort_field::OPERATOR_IS_MEMBER_OF,
            'cohort_field_field' => $datefieldfieldname,
            $datefieldfieldname . '_operator' => condition_base::DATE_IS_AFTER,
            $datefieldfieldname . '_value' => $now,
        ]);

        $this->assertSame(
            'Users who are members of cohorts with field \'Custom field\' is after ' . userdate($now),
            $condition->get_config_description()
        );

        $condition = $this->get_condition([
            'cohort_field_operator' => cohort_field::OPERATOR_IS_MEMBER_OF,
            'cohort_field_field' => $datefieldfieldname,
            $datefieldfieldname . '_operator' => condition_base::DATE_IS_BEFORE,
            $datefieldfieldname . '_value' => $now,
        ]);

        $this->assertSame(
            'Users who are members of cohorts with field \'Custom field\' is before ' . userdate($now),
            $condition->get_config_description()
        );
    }

    /**
     * Test getting SQL.
     */
    public function test_get_sql_data_custom_fields(): void {
        global $DB;

        if (!class_exists(\core_cohort\customfield\cohort_handler::class)) {
            $this->markTestSkipped();
        }

        $this->resetAfterTest();

        // We need admin to be able to add custom fields data for cohorts.
        $this->setAdminUser();

        $now = time();
        $textfield = $this->create_cohort_custom_field();
        $checkboxfield = $this->create_cohort_custom_field('checkboxfield', 'checkbox');
        $datefield = $this->create_cohort_custom_field('datefield', 'date');

        $cohort1 = $this->getDataGenerator()->create_cohort([
            'customfield_' . $textfield->get('shortname') => 'Test value 1',
            'customfield_' . $checkboxfield->get('shortname') => '1',
            'customfield_' . $datefield->get('shortname') => $now - WEEKSECS,
        ]);
        $cohort2 = $this->getDataGenerator()->create_cohort();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();

        cohort_add_member($cohort1->id, $user1->id);
        cohort_add_member($cohort1->id, $user2->id);
        cohort_add_member($cohort2->id, $user3->id);

        $totalusers = $DB->count_records('user');
        $textfieldname = cohort_field::CUSTOM_FIELD_PREFIX . $textfield->get('shortname');
        $checkboxfieldname = cohort_field::CUSTOM_FIELD_PREFIX . $checkboxfield->get('shortname');
        $datefieldname = cohort_field::CUSTOM_FIELD_PREFIX . $datefield->get('shortname');

        // User 1 and user 2 as they are members of cohort 1.
        $condition = $this->get_condition([
            'cohort_field_operator' => cohort_field::OPERATOR_IS_MEMBER_OF,
            'cohort_field_field' => $textfieldname,
            $textfieldname . '_operator' => condition_base::TEXT_IS_EQUAL_TO,
            $textfieldname . '_value' => 'Test value 1',
        ]);

        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $this->assertCount(2, $DB->get_records_sql($sql, $result->get_params()));

        // Everyone except user 1 and user 2 as they are member of cohort 1.
        $condition = $this->get_condition([
            'cohort_field_operator' => cohort_field::OPERATOR_IS_NOT_MEMBER_OF,
            'cohort_field_field' => $textfieldname,
            $textfieldname . '_operator' => condition_base::TEXT_IS_EQUAL_TO,
            $textfieldname . '_value' => 'Test value 1',
        ]);

        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $this->assertCount($totalusers - 2, $DB->get_records_sql($sql, $result->get_params()));

        // Everyone as cohort is empty.
        $condition = $this->get_condition([
            'cohort_field_operator' => cohort_field::OPERATOR_IS_NOT_MEMBER_OF,
            'cohort_field_field' => $textfieldname,
            $textfieldname . '_operator' => condition_base::TEXT_IS_EQUAL_TO,
            $textfieldname . '_value' => 'Test value 2',
        ]);

        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $this->assertCount($totalusers, $DB->get_records_sql($sql, $result->get_params()));

        // User 1, user 2 and user 3 as they are members of cohort 1 and cohort 2 (this one is with missing custom field data).
        $condition = $this->get_condition([
            'cohort_field_operator' => cohort_field::OPERATOR_IS_MEMBER_OF,
            'cohort_field_field' => $textfieldname,
            $textfieldname . '_operator' => condition_base::TEXT_IS_EQUAL_TO,
            $textfieldname . '_value' => 'Test value 1',
            $textfieldname . '_include_missing_data' => '1',
        ]);

        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $this->assertCount(3, $DB->get_records_sql($sql, $result->get_params()));

        // User 1 and user 2 as they are members of cohort 1.
        $condition = $this->get_condition([
            'cohort_field_operator' => cohort_field::OPERATOR_IS_MEMBER_OF,
            'cohort_field_field' => $checkboxfieldname,
            $checkboxfieldname . '_operator' => condition_base::TEXT_IS_EQUAL_TO,
            $checkboxfieldname . '_value' => 1,
        ]);

        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $this->assertCount(2, $DB->get_records_sql($sql, $result->get_params()));

        // User 1 and user 2 as they are members of cohort 1.
        $condition = $this->get_condition([
            'cohort_field_operator' => cohort_field::OPERATOR_IS_MEMBER_OF,
            'cohort_field_field' => $datefieldname,
            $datefieldname . '_operator' => condition_base::DATE_IS_BEFORE,
            $datefieldname . '_value' => $now,
        ]);

        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $this->assertCount(2, $DB->get_records_sql($sql, $result->get_params()));

        // All users except user 3 as he is a members of cohort 2.
        $condition = $this->get_condition([
            'cohort_field_operator' => cohort_field::OPERATOR_IS_NOT_MEMBER_OF,
            'cohort_field_field' => $datefieldname,
            $datefieldname . '_operator' => condition_base::TEXT_IS_EMPTY,
            $datefieldname . '_value' => $now,
        ]);

        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $this->assertCount($totalusers - 1, $DB->get_records_sql($sql, $result->get_params()));

        // User 1 and user 2 as they are members of cohort 1.
        $condition = $this->get_condition([
            'cohort_field_operator' => cohort_field::OPERATOR_IS_MEMBER_OF,
            'cohort_field_field' => $datefieldname,
            $datefieldname . '_operator' => condition_base::DATE_IN_THE_PAST,
            $datefieldname . '_value' => $now,
        ]);

        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $this->assertCount(2, $DB->get_records_sql($sql, $result->get_params()));

        // No cohort with a date is in the future. No users should be returned.
        $condition = $this->get_condition([
            'cohort_field_operator' => cohort_field::OPERATOR_IS_MEMBER_OF,
            'cohort_field_field' => $datefieldname,
            $datefieldname . '_operator' => condition_base::DATE_IN_THE_FUTURE,
            $datefieldname . '_value' => $now,
        ]);

        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $this->assertCount(0, $DB->get_records_sql($sql, $result->get_params()));
    }

    /**
     * Test that custom field date comparisons use numeric ordering, not lexical string ordering.
     */
    public function test_get_sql_data_custom_field_date_comparison_is_numeric(): void {
        global $DB;

        if (!class_exists(\core_cohort\customfield\cohort_handler::class)) {
            $this->markTestSkipped();
        }

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a text field to cover sql_cast_char2int regressions.
        $textfield = $this->create_cohort_custom_field('textfield', 'text');
        $datefield = $this->create_cohort_custom_field('datefield', 'date');

        // These specific values are taken from the original bug report (#160).
        $lowerdate = 952899000;
        $threshold = 1205280000;
        $higherdate = 1205280000 + 86400;

        $cohortbeforethreshold = $this->getDataGenerator()->create_cohort([
            'customfield_' . $textfield->get('shortname') => 'Test value 1',
            'customfield_' . $datefield->get('shortname') => $lowerdate,
        ]);
        $cohortafterthreshold = $this->getDataGenerator()->create_cohort([
            'customfield_' . $textfield->get('shortname') => 'Test value 2',
            'customfield_' . $datefield->get('shortname') => $higherdate,
        ]);

        $userbeforethreshold = $this->getDataGenerator()->create_user(['username' => 'datebeforethreshold']);
        $userafterthreshold = $this->getDataGenerator()->create_user(['username' => 'dateafterthreshold']);
        cohort_add_member($cohortbeforethreshold->id, $userbeforethreshold->id);
        cohort_add_member($cohortafterthreshold->id, $userafterthreshold->id);

        $datefieldname = cohort_field::CUSTOM_FIELD_PREFIX . $datefield->get('shortname');
        $condition = $this->get_condition([
            'cohort_field_operator' => cohort_field::OPERATOR_IS_MEMBER_OF,
            'cohort_field_field' => $datefieldname,
            $datefieldname . '_operator' => condition_base::DATE_IS_AFTER,
            $datefieldname . '_value' => $threshold,
        ]);

        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $users = $DB->get_records_sql($sql, $result->get_params());

        $this->assertCount(1, $users);
        $this->assertArrayHasKey($userafterthreshold->id, $users);
        $this->assertArrayNotHasKey($userbeforethreshold->id, $users);
    }

    /**
     * Test events that the condition is listening to.
     */
    public function test_get_events(): void {
        $this->assertEquals([
            '\core\event\cohort_member_added',
            '\core\event\cohort_member_removed',
        ], $this->get_condition()->get_events());
    }
}
