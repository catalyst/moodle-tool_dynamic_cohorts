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

/**
 * Tests for cohort manager class.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers     \tool_dynamic_cohorts\cohort_manager
 */
class cohort_manager_test extends \advanced_testcase {

    /**
     * Test getting available cohorts.
     */
    public function test_get_available_cohorts() {
        $this->resetAfterTest();

        $this->assertEmpty(cohort_manager::get_cohorts());

        $cohort1 = $this->getDataGenerator()->create_cohort(['component' => cohort_manager::COHORT_COMPONENT]);
        $cohort2 = $this->getDataGenerator()->create_cohort();
        $cohort3 = $this->getDataGenerator()->create_cohort();
        $cohort4 = $this->getDataGenerator()->create_cohort(['component' => 'mod_assign']);

        $allcohorts = cohort_manager::get_cohorts();

        $this->assertCount(3, $allcohorts);

        $this->assertEquals($cohort1, $allcohorts[$cohort1->id]);
        $this->assertEquals($cohort2, $allcohorts[$cohort2->id]);
        $this->assertEquals($cohort3, $allcohorts[$cohort3->id]);
        $this->assertArrayNotHasKey($cohort4->id, $allcohorts);

        $allcohorts = cohort_manager::get_cohorts(true);
        $this->assertCount(2, $allcohorts);

        $this->assertArrayNotHasKey($cohort1->id, $allcohorts);
        $this->assertEquals($cohort2, $allcohorts[$cohort2->id]);
        $this->assertEquals($cohort3, $allcohorts[$cohort3->id]);
        $this->assertArrayNotHasKey($cohort4->id, $allcohorts);
    }

    /**
     * Test managing cohort.
     */
    public function test_manage_cohort() {
        global $DB;

        $this->resetAfterTest();

        $cohort = $this->getDataGenerator()->create_cohort();
        $this->assertEquals('', $DB->get_field('cohort', 'component', ['id' => $cohort->id]));

        cohort_manager::manage_cohort($cohort->id);
        $this->assertEquals('tool_dynamic_cohorts', $DB->get_field('cohort', 'component', ['id' => $cohort->id]));
    }

    /**
     * Test unmanaging cohort.
     */
    public function test_unmanage_cohort() {
        global $DB;

        $this->resetAfterTest();

        $cohort = $this->getDataGenerator()->create_cohort(['component' => 'tool_dynamic_cohorts']);
        $this->assertEquals('tool_dynamic_cohorts', $DB->get_field('cohort', 'component', ['id' => $cohort->id]));

        cohort_manager::unmanage_cohort($cohort->id);
        $this->assertEquals('', $DB->get_field('cohort', 'component', ['id' => $cohort->id]));
    }
}
