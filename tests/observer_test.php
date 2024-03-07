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

use advanced_testcase;
use tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\user_profile;

/**
 * Unit tests for observer class.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers     \tool_dynamic_cohorts\observer
 */
class observer_test extends advanced_testcase {

    /**
     * Cohort for testing
     * @var \stdClass
     */
    protected $cohort;

    /**
     * Initial set up.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->cohort = $this->getDataGenerator()->create_cohort();
    }

    /**
     * Test that user creation event triggers rule processing for that user.
     */
    public function test_user_creation_triggers_rule_processing() {
        global $DB;

        $rule = new rule(0, (object)['name' => 'Test rule 1', 'enabled' => 1, 'cohortid' => $this->cohort->id]);
        $rule->save();

        $condition = condition_base::get_instance(0, (object)[
            'classname' => 'tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\user_profile',
        ]);

        // Condition username starts with user to catch both users.
        $condition->set_config_data([
            'profilefield' => 'username',
            'username_operator' => user_profile::TEXT_STARTS_WITH,
            'username_value' => 'user',
        ]);

        $record = $condition->get_record();
        $record->set('ruleid', $rule->get('id'));
        $record->set('sortorder', 0);
        $record->save();

        $this->assertEquals(0, $DB->count_records('cohort_members', ['cohortid' => $this->cohort->id]));

        $this->getDataGenerator()->create_user(['username' => 'user1']);
        $this->getDataGenerator()->create_user(['username' => 'user2']);

        $this->assertEquals(2, $DB->count_records('cohort_members', ['cohortid' => $this->cohort->id]));
    }

    /**
     * Test that user updating event triggers rule processing for that user.
     */
    public function test_user_updating_triggers_rule_processing() {
        global $DB;

        $user1 = $this->getDataGenerator()->create_user(['username' => 'user1']);
        $user2 = $this->getDataGenerator()->create_user(['username' => 'user2']);

        $rule = new rule(0, (object)['name' => 'Test rule 1', 'enabled' => 1, 'cohortid' => $this->cohort->id]);
        $rule->save();

        $condition = condition_base::get_instance(0, (object)[
            'classname' => 'tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\user_profile',
        ]);

        // Condition username starts with user to catch both users.
        $condition->set_config_data([
            'profilefield' => 'username',
            'username_operator' => user_profile::TEXT_STARTS_WITH,
            'username_value' => 'user',
        ]);

        $record = $condition->get_record();
        $record->set('ruleid', $rule->get('id'));
        $record->set('sortorder', 0);
        $record->save();

        $this->assertEquals(0, $DB->count_records('cohort_members', ['cohortid' => $this->cohort->id]));

        user_update_user($user1, false);
        $this->assertEquals(1, $DB->count_records('cohort_members', ['cohortid' => $this->cohort->id]));

        user_update_user($user2, false);
        $this->assertEquals(2, $DB->count_records('cohort_members', ['cohortid' => $this->cohort->id]));
    }
}
