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
 * Unit tests for cohort membership condition class.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers     \tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\cohort_membership
 */
class cohort_membership_test extends \advanced_testcase {

    /**
     * Get condition instance for testing.
     *
     * @param array $configdata Config data to be set.
     * @return condition_base
     */
    protected function get_condition(array $configdata = []): condition_base {
        $condition = condition_base::get_instance(0, (object)[
            'classname' => '\tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\cohort_membership',
        ]);
        $condition->set_config_data($configdata);

        return $condition;
    }

    /**
     * Test class constants.
     */
    public function test_constants() {
        $this->assertSame('cohort_membership', cohort_membership::FIELD_NAME);
        $this->assertSame(1, cohort_membership::OPERATOR_IS_MEMBER_OF);
        $this->assertSame(2, cohort_membership::OPERATOR_IS_NOT_MEMBER_OF);
    }

    /**
     * Test retrieving of config data.
     */
    public function test_retrieving_configdata() {
        $formdata = (object)[
            'id' => 1,
            'cohort_membership_operator' => 3,
            'cohort_membership_value' => 123,
            'ruleid' => 1,
            'sortorder' => 0,
        ];

        $actual = $this->get_condition()::retrieve_config_data($formdata);
        $expected = [
            'cohort_membership_operator' => 3,
            'cohort_membership_value' => 123,
        ];
        $this->assertEquals($expected, $actual);
    }

    /**
     * Test setting and getting config data.
     */
    public function test_set_and_get_configdata() {
        $condition = $this->get_condition([
            'cohort_membership_operator' => 3,
            'cohort_membership_value' => 123,
        ]);

        $this->assertEquals(
            ['cohort_membership_operator' => 3,  'cohort_membership_value' => 123],
            $condition->get_config_data()
        );
    }

    /**
     * Test getting config description.
     */
    public function test_config_description() {
        $this->resetAfterTest();

        $cohort1 = $this->getDataGenerator()->create_cohort();
        $cohort2 = $this->getDataGenerator()->create_cohort();

        $condition = $this->get_condition([
            'cohort_membership_operator' => cohort_membership::OPERATOR_IS_MEMBER_OF,
            'cohort_membership_value' => [$cohort1->id],
        ]);
        $this->assertSame('A user is member of ' . $cohort1->name, $condition->get_config_description());

        $condition = $this->get_condition([
            'cohort_membership_operator' => cohort_membership::OPERATOR_IS_NOT_MEMBER_OF,
            'cohort_membership_value' => [$cohort1->id, $cohort2->id],
        ]);
        $this->assertSame(
            'A user is not member of ' . $cohort1->name . ' OR ' . $cohort2->name,
            $condition->get_config_description()
        );

        $condition = $this->get_condition([
            'cohort_membership_operator' => cohort_membership::OPERATOR_IS_MEMBER_OF,
            'cohort_membership_value' => [$cohort1->id, $cohort2->id, 777],
        ]);
        $this->assertSame(
            'A user is member of ' . $cohort1->name . ' OR ' . $cohort2->name . ' OR ' . 777,
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
        $this->resetAfterTest();

        $cohort1 = $this->getDataGenerator()->create_cohort();
        $cohort2 = $this->getDataGenerator()->create_cohort();

        $rule = new rule(0, (object)['name' => 'Test rule 1', 'cohortid' => $cohort1->id]);
        $rule->save();

        // Should be ok by default.
        $condition = cohort_membership::get_instance(0, (object)['ruleid' => $rule->get('id')]);
        $this->assertFalse($condition->is_broken());

        // Existing cohort.
        $condition->set_config_data([
            'cohort_membership_operator' => cohort_membership::OPERATOR_IS_MEMBER_OF,
            'cohort_membership_value' => [$cohort2->id],
        ]);
        $this->assertFalse($condition->is_broken());

        // Non existing cohort.
        $condition->set_config_data([
            'cohort_membership_operator' => cohort_membership::OPERATOR_IS_MEMBER_OF,
            'cohort_membership_value' => [777],
        ]);
        $this->assertTrue($condition->is_broken());

        // Cohort is taken by a rule.
        $condition->set_config_data([
            'cohort_membership_operator' => cohort_membership::OPERATOR_IS_MEMBER_OF,
            'cohort_membership_value' => [$cohort1->id],
        ]);
        $this->assertTrue($condition->is_broken());

        // One of the cohorts is taken by a rule.
        $condition->set_config_data([
            'cohort_membership_operator' => cohort_membership::OPERATOR_IS_MEMBER_OF,
            'cohort_membership_value' => [$cohort1->id, $cohort2->id],
        ]);
        $this->assertTrue($condition->is_broken());
    }

    /**
     * Test getting broken description.
     */
    public function test_get_broken_description() {
        $this->resetAfterTest();

        $cohort1 = $this->getDataGenerator()->create_cohort();
        $cohort2 = $this->getDataGenerator()->create_cohort();

        $rule = new rule(0, (object)['name' => 'Test rule 1', 'cohortid' => $cohort1->id]);
        $rule->save();

        // Default broken description.
        $condition = cohort_membership::get_instance(0, (object)['ruleid' => $rule->get('id')]);
        $condition->set_config_data([
            'cohort_membership_operator' => cohort_membership::OPERATOR_IS_MEMBER_OF,
            'cohort_membership_value' => [$cohort2->id],
        ]);

        $this->assertSame(
            '{"cohort_membership_operator":1,"cohort_membership_value":["' . $cohort2->id . '"]}',
            $condition->get_broken_description()
        );

        // Broken description if using the same cohort as the rule does.
        $condition->set_config_data([
            'cohort_membership_operator' => cohort_membership::OPERATOR_IS_MEMBER_OF,
            'cohort_membership_value' => [$cohort1->id, $cohort2->id],
        ]);
        $this->assertSame(
            get_string('condition:cohort_membership_broken_description', 'tool_dynamic_cohorts')
            . '<br />'
            . "A user is member of {$cohort1->name} OR {$cohort2->name}",
            $condition->get_broken_description()
        );
    }

    /**
     * Test setting and getting config data.
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
            'cohort_membership_operator' => cohort_membership::OPERATOR_IS_MEMBER_OF,
            'cohort_membership_value' => [$cohort1->id],
        ]);
        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        // User 1 and user 2 as they are members of cohort 1.
        $this->assertCount(2, $DB->get_records_sql($sql, $result->get_params()));

        $condition = $this->get_condition([
            'cohort_membership_operator' => cohort_membership::OPERATOR_IS_MEMBER_OF,
            'cohort_membership_value' => [$cohort2->id],
        ]);
        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        // Cohort is empty.
        $this->assertCount(0, $DB->get_records_sql($sql, $result->get_params()));

        $condition = $this->get_condition([
            'cohort_membership_operator' => cohort_membership::OPERATOR_IS_MEMBER_OF,
            'cohort_membership_value' => [$cohort1->id, $cohort2->id],
        ]);
        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        // User 1 and user 2 as they are members of cohort 1.
        $this->assertCount(2, $DB->get_records_sql($sql, $result->get_params()));

        $condition = $this->get_condition([
            'cohort_membership_operator' => cohort_membership::OPERATOR_IS_NOT_MEMBER_OF,
            'cohort_membership_value' => [$cohort1->id],
        ]);
        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        // Everyone except user 1 and user 2 as they are member of cohort 1.
        $this->assertCount($totalusers - 2, $DB->get_records_sql($sql, $result->get_params()));

        $condition = $this->get_condition([
            'cohort_membership_operator' => cohort_membership::OPERATOR_IS_NOT_MEMBER_OF,
            'cohort_membership_value' => [$cohort2->id],
        ]);
        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        // Everyone as cohort is empty.
        $this->assertCount($totalusers, $DB->get_records_sql($sql, $result->get_params()));

        $condition = $this->get_condition([
            'cohort_membership_operator' => cohort_membership::OPERATOR_IS_NOT_MEMBER_OF,
            'cohort_membership_value' => [$cohort1->id, $cohort2->id],
        ]);
        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        // Everyone except user 1 and user 2 as they are member of cohort 1.
        $this->assertCount($totalusers - 2, $DB->get_records_sql($sql, $result->get_params()));
    }

    /**
     * Test events that the condition is listening to.
     */
    public function test_get_events() {
        $this->assertEquals([
            '\core\event\cohort_member_added',
            '\core\event\cohort_member_removed',
        ], $this->get_condition()->get_events());
    }

}
