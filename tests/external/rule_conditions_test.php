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

namespace tool_dynamic_cohorts\external;

use externallib_advanced_testcase;
use tool_dynamic_cohorts\condition;
use tool_dynamic_cohorts\rule;
use tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\cohort_membership;
use tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\user_profile;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Tests for matching users external APIs .
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

 * @runTestsInSeparateProcesses
 * @covers     \tool_dynamic_cohorts\external\rule_conditions
 */
class rule_conditions_test extends externallib_advanced_testcase {

    /**
     * Test exception if rule is not exist.
     */
    public function test_get_conditions_throws_exception_on_invalid_rule() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $this->expectException(\invalid_parameter_exception::class);
        $this->expectExceptionMessage('Rule does not exist');

        rule_conditions::get_conditions(777);
    }

    /**
     * Test required permissions.
     */
    public function test_get_total_permissions() {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->expectException(\required_capability_exception::class);
        $this->expectExceptionMessage('Sorry, but you do not currently have permissions to do that (Manage rules).');

        rule_conditions::get_conditions(777);
    }

    /**
     * Test getting conditions for empty rule.
     */
    public function test_get_conditions_for_empty_rule() {
        $this->resetAfterTest();
        $this->setAdminUser();

        $rule = new rule(0, (object)['name' => 'Test rule 1']);
        $rule->save();

        $conditions = rule_conditions::get_conditions($rule->get('id'));
        $this->assertIsArray($conditions);
        $this->assertEmpty($conditions);
    }

    /**
     * Test getting conditions for aa rule.
     */
    public function test_get_conditions_for_rule() {
        $this->resetAfterTest();
        $this->setAdminUser();

        $rule = new rule(0, (object)['name' => 'Test rule 1']);
        $rule->save();

        $condition1 = cohort_membership::get_instance(0, (object)['ruleid' => $rule->get('id'), 'sortorder' => 0]);
        $condition1->set_config_data([
            'cohort_membership_operator' => cohort_membership::OPERATOR_IS_MEMBER_OF,
            'cohort_membership_value' => [777],
        ]);
        $condition1->get_record()->save();

        $condition2 = user_profile::get_instance(0, (object)['ruleid' => $rule->get('id'), 'sortorder' => 1]);
        $condition2->set_config_data([
            'profilefield' => 'username',
            'username_operator' => user_profile::TEXT_IS_EQUAL_TO,
            'username_value' => 'user1username',
        ]);
        $condition2->get_record()->save();

        $condition3 = new condition(0, (object)['ruleid' => $rule->get('id'), 'classname' => 'test', 'sortorder' => 2]);
        $condition3->save();

        // Broken condition.
        $condition4 = user_profile::get_instance(0, (object)['ruleid' => $rule->get('id'), 'sortorder' => 1]);
        $condition4->set_config_data([
            'profilefield' => 'username',
            'username_operator' => user_profile::TEXT_IS_EQUAL_TO,
            'username_value' => '',
        ]);
        $condition4->get_record()->save();

        $conditions = rule_conditions::get_conditions($rule->get('id'));
        $this->assertIsArray($conditions);
        $this->assertCount(4, $conditions);

        $this->assertArrayHasKey($condition1->get_record()->get('id'), $conditions);
        $this->assertArrayHasKey($condition2->get_record()->get('id'), $conditions);
        $this->assertArrayHasKey($condition3->get('id'), $conditions);

        $this->assertSame($condition1->get_record()->get('id'), $conditions[$condition1->get_record()->get('id')]['id']);
        $this->assertSame(0, $conditions[$condition1->get_record()->get('id')]['sortorder']);
        $this->assertSame(
            'tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\cohort_membership',
            $conditions[$condition1->get_record()->get('id')]['classname']
        );
        $this->assertSame(
            '{"cohort_membership_operator":1,"cohort_membership_value":[777]}',
            $conditions[$condition1->get_record()->get('id')]['configdata']
        );
        $this->assertSame(
            '{"cohort_membership_operator":1,"cohort_membership_value":[777]}',
            $conditions[$condition1->get_record()->get('id')]['description']
        );
        $this->assertSame('Cohort membership', $conditions[$condition1->get_record()->get('id')]['name']);

        $this->assertSame($condition2->get_record()->get('id'), $conditions[$condition2->get_record()->get('id')]['id']);
        $this->assertSame(1, $conditions[$condition2->get_record()->get('id')]['sortorder']);
        $this->assertSame(
            'tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\user_profile',
            $conditions[$condition2->get_record()->get('id')]['classname']
        );
        $this->assertSame(
            '{"profilefield":"username","username_operator":3,"username_value":"user1username"}',
            $conditions[$condition2->get_record()->get('id')]['configdata']
        );
        $this->assertSame('Username is equal to user1username', $conditions[$condition2->get_record()->get('id')]['description']);
        $this->assertSame('User standard profile field', $conditions[$condition2->get_record()->get('id')]['name']);

        $this->assertSame($condition3->get('id'), $conditions[$condition3->get('id')]['id']);
        $this->assertSame(2, $conditions[$condition3->get('id')]['sortorder']);
        $this->assertSame('test', $conditions[$condition3->get('id')]['classname']);
        $this->assertSame('{}', $conditions[$condition3->get('id')]['configdata']);
        $this->assertSame('{}', $conditions[$condition3->get('id')]['description']);
        $this->assertSame('test', $conditions[$condition3->get('id')]['name']);

        $this->assertSame($condition4->get_record()->get('id'), $conditions[$condition4->get_record()->get('id')]['id']);
        $this->assertSame(1, $conditions[$condition4->get_record()->get('id')]['sortorder']);
        $this->assertSame(
            'tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\user_profile',
            $conditions[$condition4->get_record()->get('id')]['classname']
        );
        $this->assertSame(
            '{"profilefield":"username","username_operator":3,"username_value":""}',
            $conditions[$condition4->get_record()->get('id')]['configdata']
        );
        $this->assertSame('Username is equal to user1username', $conditions[$condition2->get_record()->get('id')]['description']);
        $this->assertSame('User standard profile field', $conditions[$condition2->get_record()->get('id')]['name']);

        $this->assertSame(true, $conditions[$condition1->get_record()->get('id')]['broken']);
        $this->assertSame(false, $conditions[$condition2->get_record()->get('id')]['broken']);
        $this->assertSame(false, $conditions[$condition3->get('id')]['broken']);
        $this->assertSame(true, $conditions[$condition4->get_record()->get('id')]['broken']);
    }
}
