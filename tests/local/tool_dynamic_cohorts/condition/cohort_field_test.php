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
class cohort_field_test extends \advanced_testcase {

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
    public function test_retrieving_configdata() {
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
    public function test_set_and_get_configdata() {
        $instance = $this->get_condition([
            'cohort_field_operator' => 2,
            'cohort_field_field' => 'visible',
            'visible_operator' => 3,
            'visible_value' => 0,
        ]);

        $this->assertEquals(
            ['cohort_field_field' => 'visible',  'cohort_field_operator' => 2,  'visible_operator' => 3, 'visible_value' => 0],
            $instance->get_config_data()
        );
    }

    /**
     * Data provider for testing config description.
     *
     * @return array[]
     */
    public function config_description_data_provider(): array {
        return [
            [cohort_field::TEXT_CONTAINS, 'A user is not member of cohorts with field \'theme\' contains 123'],
            [cohort_field::TEXT_DOES_NOT_CONTAIN, 'A user is not member of cohorts with field \'theme\' doesn\'t contain 123'],
            [cohort_field::TEXT_IS_EQUAL_TO, 'A user is not member of cohorts with field \'theme\' is equal to 123'],
            [cohort_field::TEXT_IS_NOT_EQUAL_TO, 'A user is not member of cohorts with field \'theme\' isn\'t equal to 123'],
            [cohort_field::TEXT_STARTS_WITH, 'A user is not member of cohorts with field \'theme\' starts with 123'],
            [cohort_field::TEXT_ENDS_WITH, 'A user is not member of cohorts with field \'theme\' ends with 123'],
            [cohort_field::TEXT_IS_EMPTY, 'A user is not member of cohorts with field \'theme\' is empty '],
            [cohort_field::TEXT_IS_NOT_EMPTY, 'A user is not member of cohorts with field \'theme\' is not empty '],
        ];
    }

    /**
     * Test getting config description.
     *
     * @dataProvider config_description_data_provider
     * @param int $operator
     * @param string $expected
     */
    public function test_config_description(int $operator, string $expected) {
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
    public function test_config_description_context_id() {
        $this->resetAfterTest();

        $coursecategory = $this->getDataGenerator()->create_category();
        $catcontext = \context_coursecat::instance($coursecategory->id);

        $condition = $this->get_condition([
            'cohort_field_operator' => cohort_field::OPERATOR_IS_MEMBER_OF,
            'cohort_field_field' => 'contextid',
            'contextid_operator' => cohort_field::TEXT_IS_EQUAL_TO,
            'contextid_value' => $catcontext->id,
        ]);

        $this->assertSame(
            'A user is member of cohorts with field \'contextid\' is equal to ' . $coursecategory->name,
            $condition->get_config_description()
        );
    }

    /**
     * Test getting rule.
     */
    public function test_get_rule() {
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
    public function test_is_broken() {
        $condition = $this->get_condition();

        // Not configured should be always valid.
        $this->assertFalse($condition->is_broken());

        // Context is not exist/gone.
        $condition = $this->get_condition([
            'cohort_field_operator' => cohort_field::OPERATOR_IS_MEMBER_OF,
            'cohort_field_field' => 'contextid',
            'contextid_operator' => cohort_field::TEXT_IS_EQUAL_TO,
            'contextid_value' => 7777,
        ]);
        $this->assertTrue($condition->is_broken());

        $condition = $this->get_condition([
            'cohort_field_operator' => cohort_field::OPERATOR_IS_MEMBER_OF,
            'cohort_field_field' => 'name',
            'name_operator' => cohort_field::TEXT_IS_EMPTY,
            'name_value' => '',
        ]);
        $this->assertFalse($condition->is_broken());

        $condition = $this->get_condition([
            'cohort_field_operator' => cohort_field::OPERATOR_IS_MEMBER_OF,
            'cohort_field_field' => 'name',
            'name_operator' => cohort_field::TEXT_IS_NOT_EMPTY,
            'name_value' => '',
        ]);
        $this->assertFalse($condition->is_broken());

        $condition = $this->get_condition([
            'cohort_field_operator' => cohort_field::OPERATOR_IS_MEMBER_OF,
            'cohort_field_field' => 'name',
            'name_operator' => cohort_field::TEXT_IS_EQUAL_TO,
            'name_value' => '',
        ]);
        $this->assertTrue($condition->is_broken());

        $condition = $this->get_condition([
            'cohort_field_operator' => cohort_field::OPERATOR_IS_MEMBER_OF,
            'cohort_field_field' => 'notexists',
            'notexists_operator' => cohort_field::TEXT_IS_EQUAL_TO,
            'notexists_value' => '',
        ]);
        $this->assertTrue($condition->is_broken());
    }

    /**
     * Test getting SQL.
     */
    public function test_get_sql_data() {
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
     * Test events that the condition is listening to.
     */
    public function test_get_events() {
        $this->assertEquals([
            '\core\event\cohort_updated',
            '\core\event\cohort_member_added',
            '\core\event\cohort_member_removed',
        ], $this->get_condition()->get_events());
    }
}
