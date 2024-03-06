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
 * Unit tests for user profile condition class.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\user_profile
 */
class user_profile_test extends \advanced_testcase {

    /**
     * Get condition instance for testing.
     *
     * @param array $configdata Config data to be set.
     * @return condition_base
     */
    protected function get_condition(array $configdata = []): condition_base {
        $condition = condition_base::get_instance(0, (object)[
            'classname' => '\tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\user_profile',
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
            'profilefield' => 'firstname',
            'firstname_operator' => 3,
            'firstname_value' => 123,
            'invalid_firstname' => 'invalid',
            'ruleid' => 1,
            'position' => 0,
        ];

        $actual = $this->get_condition()::retrieve_config_data($formdata);
        $expected = [
            'profilefield' => 'firstname',
            'firstname_operator' => 3,
            'firstname_value' => 123,
        ];
        $this->assertEquals($expected, $actual);
    }

    /**
     * Test setting and getting config data.
     */
    public function test_set_and_get_configdata() {
        $instance = $this->get_condition([
            'profilefield' => 'firstname',
            'firstname_operator' => 3,
            'firstname_value' => 123,
        ]);

        $this->assertEquals(
            ['profilefield' => 'firstname',  'firstname_operator' => 3,  'firstname_value' => 123],
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
            [user_profile::TEXT_CONTAINS, 'First name contains 123'],
            [user_profile::TEXT_DOES_NOT_CONTAIN, 'First name doesn\'t contain 123'],
            [user_profile::TEXT_IS_EQUAL_TO, 'First name is equal to 123'],
            [user_profile::TEXT_IS_NOT_EQUAL_TO, 'First name isn\'t equal to 123'],
            [user_profile::TEXT_STARTS_WITH, 'First name starts with 123'],
            [user_profile::TEXT_ENDS_WITH, 'First name ends with 123'],
            [user_profile::TEXT_IS_EMPTY, 'First name is empty'],
            [user_profile::TEXT_IS_NOT_EMPTY, 'First name is not empty'],
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
        $instance = $this->get_condition([
            'profilefield' => 'firstname',
            'firstname_operator' => $operator,
            'firstname_value' => '123',
        ]);

        $this->assertSame($expected, $instance->get_config_description());
    }

    /**
     * Test getting config description.
     */
    public function test_config_description_for_auth_field() {
        $instance = $this->get_condition([
            'profilefield' => 'auth',
            'auth_operator' => user_profile::TEXT_IS_EQUAL_TO,
            'auth_value' => 'manual',
        ]);

        $this->assertSame('Authentication method is equal to Manual accounts', $instance->get_config_description());
    }

    /**
     * Test setting and getting config data.
     */
    public function test_get_sql_data() {
        global $DB;

        $this->resetAfterTest();

        $this->getDataGenerator()->create_user(['username' => 'user1username']);
        $this->getDataGenerator()->create_user(['username' => 'user2username']);

        $condition = $this->get_condition([
            'profilefield' => 'username',
            'username_operator' => user_profile::TEXT_IS_EQUAL_TO,
            'username_value' => 'user1username',
        ]);

        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $this->assertCount(1, $DB->get_records_sql($sql, $result->get_params()));

        $condition->set_config_data([
            'profilefield' => 'username',
            'username_operator' => user_profile::TEXT_STARTS_WITH,
            'username_value' => 'user',
        ]);

        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $this->assertCount(2, $DB->get_records_sql($sql, $result->get_params()));

        $condition->set_config_data([
            'profilefield' => 'username',
            'username_operator' => user_profile::TEXT_ENDS_WITH,
            'username_value' => 'username',
        ]);

        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $this->assertCount(2, $DB->get_records_sql($sql, $result->get_params()));

        $condition->set_config_data([
            'profilefield' => 'username',
            'username_operator' => user_profile::TEXT_IS_NOT_EQUAL_TO,
            'username_value' => 'user1username',
        ]);

        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $totalusers = $DB->count_records('user');
        $this->assertCount($totalusers - 1, $DB->get_records_sql($sql, $result->get_params()));
    }

    /**
     * Test events that the condition is listening to.
     */
    public function test_get_events() {
        $this->assertEquals([
            '\core\event\user_created',
            '\core\event\user_updated',
        ], $this->get_condition()->get_events());
    }

    /**
     * Test is broken.
     */
    public function test_is_broken() {
        $condition = $this->get_condition();

        // Not configured should be always valid.
        $this->assertFalse($condition->is_broken());

        $condition->set_config_data([
            'profilefield' => 'username',
            'username_operator' => user_profile::TEXT_IS_EMPTY,
            'username_value' => '',
        ]);
        $this->assertFalse($condition->is_broken());

        $condition->set_config_data([
            'profilefield' => 'username',
            'username_operator' => user_profile::TEXT_IS_NOT_EMPTY,
            'username_value' => '',
        ]);
        $this->assertFalse($condition->is_broken());

        // Break condition.
        $condition->set_config_data([
            'profilefield' => 'username',
            'username_operator' => user_profile::TEXT_IS_EQUAL_TO,
            'username_value' => '',
        ]);
        $this->assertTrue($condition->is_broken());

        // Break condition.
        $condition->set_config_data([
            'profilefield' => 'notexisting',
            'username_operator' => user_profile::TEXT_IS_EQUAL_TO,
            'username_value' => '123',
        ]);
        $this->assertTrue($condition->is_broken());
    }
}
