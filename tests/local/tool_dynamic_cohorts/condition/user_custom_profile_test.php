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
 * @covers     \tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\user_custom_profile
 */
class user_custom_profile_test extends \advanced_testcase {

    /**
     * Get condition instance for testing.
     *
     * @param array $configdata Config data to be set.
     * @return condition_base
     */
    protected function get_condition(array $configdata = []): condition_base {
        $condition = condition_base::get_instance(0, (object)[
            'classname' => '\tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\user_custom_profile',
        ]);
        $condition->set_config_data($configdata);

        return $condition;
    }

    /**
     * A helper function to create a custom profile field.
     *
     * @param string $shortname Short name of the field.
     * @param string $datatype Type of the field, e.g. text, checkbox, datetime, menu and etc.
     * @param array $extras A list of extra fields for the field (e.g. forceunique, param1 and etc)
     *
     * @return \stdClass
     */
    protected function add_user_profile_field(string $shortname, string $datatype, array $extras = []): \stdClass {
        global $DB;

        $data = new \stdClass();
        $data->shortname = $shortname;
        $data->datatype = $datatype;
        $data->name = 'Test ' . $shortname;
        $data->description = 'This is a test field';
        $data->required = false;
        $data->locked = false;
        $data->forceunique = false;
        $data->signup = false;
        $data->visible = '0';
        $data->categoryid = '0';

        foreach ($extras as $name => $value) {
            $data->{$name} = $value;
        }

        $DB->insert_record('user_info_field', $data);

        return $data;
    }

    /**
     * Test retrieving of config data.
     */
    public function test_retrieving_configdata() {
        // Without missing data field.
        $formdata = [
            'id' => 1,
            'profilefield' => 'firstname',
            'firstname_operator' => 3,
            'firstname_value' => 123,
            'invalid_firstname' => 'invalid',
            'ruleid' => 1,
            'sortorder' => 0,
        ];

        $actual = $this->get_condition()::retrieve_config_data((object)$formdata);
        $expected = [
            'profilefield' => 'firstname',
            'firstname_operator' => 3,
            'firstname_value' => 123,
            'include_missing_data' => 0,
        ];
        $this->assertEquals($expected, $actual);

        // Now include missing data.
        $formdata = [
            'id' => 1,
            'profilefield' => 'firstname',
            'firstname_operator' => 3,
            'firstname_value' => 123,
            'invalid_firstname' => 'invalid',
            'ruleid' => 1,
            'sortorder' => 0,
            'include_missing_data' => 1,
        ];
        $actual = $this->get_condition()::retrieve_config_data((object)$formdata);
        $expected = [
            'profilefield' => 'firstname',
            'firstname_operator' => 3,
            'firstname_value' => 123,
            'include_missing_data' => 1,
        ];
        $this->assertEquals($expected, $actual);
    }


    /**
     * Test setting and getting config data.
     */
    public function test_set_and_get_configdata() {
        // Without missing data field.
        $configdata = [
            'profilefield' => 'firstname',
            'firstname_operator' => 3,
            'firstname_value' => 123,
            'include_missing_data' => 1,
        ];

        $instance = $this->get_condition($configdata);

        $this->assertEquals(
            ['profilefield' => 'firstname',  'firstname_operator' => 3,  'firstname_value' => 123, 'include_missing_data' => 1],
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
            [condition_base::TEXT_CONTAINS, 'Test field1 contains 123', true],
            [condition_base::TEXT_DOES_NOT_CONTAIN, 'Test field1 doesn\'t contain 123', true],
            [condition_base::TEXT_IS_EQUAL_TO, 'Test field1 is equal to 123', true],
            [condition_base::TEXT_IS_NOT_EQUAL_TO, 'Test field1 isn\'t equal to 123', true],
            [condition_base::TEXT_STARTS_WITH, 'Test field1 starts with 123', true],
            [condition_base::TEXT_ENDS_WITH, 'Test field1 ends with 123', true],
            [condition_base::TEXT_IS_EMPTY, 'Test field1 is empty ', true],
            [condition_base::TEXT_IS_NOT_EMPTY, 'Test field1 is not empty ', true],
        ];
    }

    /**
     * Test getting config description.
     *
     * @dataProvider config_description_data_provider
     * @param int $operator
     * @param string $expected
     * @param bool $shouldincludemissing
     */
    public function test_config_description(int $operator, string $expected, bool $shouldincludemissing) {
        $this->resetAfterTest();

        $this->add_user_profile_field('field1', 'text');

        $configdata = [
            'profilefield' => 'profile_field_field1',
            'profile_field_field1_operator' => $operator,
            'profile_field_field1_value' => '123',
        ];

        $instance = $this->get_condition();
        $instance->set_config_data($configdata);
        $this->assertSame($expected, $instance->get_config_description());

        // Now test with including missing data.
        $configdata = [
            'profilefield' => 'profile_field_field1',
            'profile_field_field1_operator' => $operator,
            'profile_field_field1_value' => '123',
            'include_missing_data' => 1,
        ];

        $instance = $this->get_condition();
        $instance->set_config_data($configdata);
        if ($shouldincludemissing) {
            $expected .= ' (including users with missing data)';
        }
        $this->assertSame($expected, $instance->get_config_description());
    }

    /**
     * Test setting and getting config data.
     */
    public function test_get_sql_data() {
        global $DB;

        $this->resetAfterTest();

        $fieldtext1 = $this->add_user_profile_field('field1', 'text');
        $fieldtext2 = $this->add_user_profile_field('field2', 'text', ['param1' => "Opt 1\nOpt 2\nOpt 3"]);
        $fieldcheckbox = $this->add_user_profile_field('field3', 'checkbox');

        $user1 = $this->getDataGenerator()->create_user(['username' => 'user1']);
        profile_save_data((object)['id' => $user1->id, 'profile_field_' . $fieldtext1->shortname => 'User 1 Field 1']);
        profile_save_data((object)['id' => $user1->id, 'profile_field_' . $fieldtext2->shortname => 'Opt 1']);
        profile_save_data((object)['id' => $user1->id, 'profile_field_' . $fieldcheckbox->shortname => '1']);

        $user2 = $this->getDataGenerator()->create_user(['username' => 'user2']);
        profile_save_data((object)['id' => $user2->id, 'profile_field_' . $fieldtext1->shortname => 'User 2 Field 1']);
        profile_save_data((object)['id' => $user2->id, 'profile_field_' . $fieldtext2->shortname => 'Opt 2']);
        profile_save_data((object)['id' => $user2->id, 'profile_field_' . $fieldcheckbox->shortname => '0']);

        $totalusers = $DB->count_records('user');

        $condition = $this->get_condition();

        $fieldname = 'profile_field_' . $fieldtext1->shortname;
        $condition->set_config_data([
            'profilefield' => $fieldname,
            $fieldname . '_operator' => condition_base::TEXT_ENDS_WITH,
            $fieldname . '_value' => 'Field 1',
        ]);

        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $this->assertCount(2, $DB->get_records_sql($sql, $result->get_params()));

        $fieldname = 'profile_field_' . $fieldtext2->shortname;
        $condition->set_config_data([
            'profilefield' => $fieldname,
            $fieldname . '_operator' => condition_base::TEXT_IS_NOT_EQUAL_TO,
            $fieldname . '_value' => 'Opt 1',
        ]);

        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $this->assertCount(1, $DB->get_records_sql($sql, $result->get_params()));

        $fieldname = 'profile_field_' . $fieldtext2->shortname;
        $condition->set_config_data([
            'profilefield' => $fieldname,
            $fieldname . '_operator' => condition_base::TEXT_IS_NOT_EQUAL_TO,
            $fieldname . '_value' => 'Opt 1',
            'include_missing_data' => 1,
        ]);

        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $this->assertCount($totalusers - 1, $DB->get_records_sql($sql, $result->get_params()));

        $fieldname = 'profile_field_' . $fieldtext2->shortname;
        $condition->set_config_data([
            'profilefield' => $fieldname,
            $fieldname . '_operator' => condition_base::TEXT_IS_NOT_EQUAL_TO,
            $fieldname . '_value' => 'Opt 1',
            'include_missing_data' => 1,
        ]);

        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $this->assertCount($totalusers - 1, $DB->get_records_sql($sql, $result->get_params()));

        $fieldname = 'profile_field_' . $fieldcheckbox->shortname;
        $condition->set_config_data([
            'profilefield' => $fieldname,
            $fieldname . '_operator' => condition_base::TEXT_IS_EQUAL_TO,
            $fieldname . '_value' => '1',
            'include_missing_data' => 0,
        ]);

        $result = $condition->get_sql();
        $sql = "SELECT u.id FROM {user} u {$result->get_join()} WHERE {$result->get_where()}";
        $this->assertCount(1, $DB->get_records_sql($sql, $result->get_params()));
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
        $this->resetAfterTest();

        $condition = $this->get_condition();

        $field1 = $this->add_user_profile_field('field1', 'text');

        // Not configured should be always valid.
        $this->assertFalse($condition->is_broken());

        $fieldname = 'profile_field_' . $field1->shortname;

        $condition->set_config_data([
            'profilefield' => $fieldname,
            $fieldname . '_operator' => condition_base::TEXT_IS_EMPTY,
            $fieldname . '_value' => '',
        ]);
        $this->assertFalse($condition->is_broken());

        $condition->set_config_data([
            'profilefield' => $fieldname,
            $fieldname . '_operator' => condition_base::TEXT_IS_NOT_EMPTY,
            $fieldname . '_value' => '',
        ]);
        $this->assertFalse($condition->is_broken());

        // Break condition.
        $condition->set_config_data([
            'profilefield' => $fieldname,
            $fieldname . '_operator' => condition_base::TEXT_IS_EQUAL_TO,
            $fieldname . '_value' => '',
        ]);
        $this->assertTrue($condition->is_broken());

        // Break condition.
        $condition->set_config_data([
            'profilefield' => 'notexisting',
            $fieldname . '_operator' => condition_base::TEXT_IS_EQUAL_TO,
            $fieldname . '_value' => '123',
        ]);
        $this->assertTrue($condition->is_broken());
    }
}
