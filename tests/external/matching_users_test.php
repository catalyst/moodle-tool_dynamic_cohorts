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
use tool_dynamic_cohorts\rule;
use tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\user_profile;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Tests for matching users external APIs .
 *
 * @package    tool_dynamic_cohorts
 * @copyright  2024 Catalyst IT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @runTestsInSeparateProcesses
 * @covers     \tool_dynamic_cohorts\external\matching_users
 */
class matching_users_test extends externallib_advanced_testcase {

    /**
     * Test exception if rule is not exist.
     */
    public function test_get_total_throws_exception_on_invalid_rule() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $this->expectException(\invalid_parameter_exception::class);
        $this->expectExceptionMessage('Rule does not exist');

        matching_users::get_total(777);
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

        matching_users::get_total(777);
    }

    /**
     * Test can get total.
     */
    public function test_get_total_empty() {
        $this->resetAfterTest();
        $this->setAdminUser();

        $rule = new rule(0, (object)['name' => 'Test rule 1']);
        $rule->save();

        $this->assertSame(0, matching_users::get_total($rule->get('id')));
    }

    /**
     * Test can get total.
     */
    public function test_get_total() {
        $this->resetAfterTest();

        $this->setAdminUser();

        $user1 = $this->getDataGenerator()->create_user(['username' => 'user1username']);
        $user2 = $this->getDataGenerator()->create_user(['username' => 'user2username']);
        $user3 = $this->getDataGenerator()->create_user(['username' => 'test']);

        $cohort = $this->getDataGenerator()->create_cohort();

        $rule = new rule(0, (object)['name' => 'Test rule 1', 'cohortid' => $cohort->id]);
        $rule->save();

        $condition = user_profile::get_instance(0, (object)['ruleid' => $rule->get('id'), 'sortorder' => 1]);
        $condition->set_config_data([
            'profilefield' => 'username',
            'username_operator' => user_profile::TEXT_IS_EQUAL_TO,
            'username_value' => 'user1username',
        ]);
        $condition->get_record()->save();

        $this->assertSame(1, matching_users::get_total($rule->get('id')));
    }
}
