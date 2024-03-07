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

use cache;

/**
 * Tests for rule class.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers     \tool_dynamic_cohorts\rule

 */
class rule_test extends \advanced_testcase {

    /**
     * Test is_enabled.
     */
    public function test_is_enabled() {
        $this->resetAfterTest();

        $rule = new rule(0, (object)['name' => 'Test rule disabled']);
        $this->assertFalse($rule->is_enabled());

        $rule = new rule(0, (object)['name' => 'Test rule enabled', 'enabled' => 1]);
        $this->assertTrue($rule->is_enabled());
    }

    /**
     * Test is_bulk_processing.
     */
    public function test_is_bulk_processing() {
        $this->resetAfterTest();

        $rule = new rule(0, (object)['name' => 'Test rule bulk processing', 'bulkprocessing' => 1]);
        $this->assertTrue($rule->is_bulk_processing());

        $rule = new rule(0, (object)['name' => 'Test rule normal']);
        $this->assertFalse($rule->is_bulk_processing());
    }

    /**
     * Test getting a list of related condition records.
     */
    public function test_get_condition_records() {
        $this->resetAfterTest();

        $rule = new rule(0, (object)['name' => 'Test rule with conditions']);
        $rule->save();

        $this->assertEmpty($rule->get_condition_records());

        $condition1 = new condition(0, (object)['ruleid' => $rule->get('id'), 'classname' => 'test', 'sortorder' => 0]);
        $condition1->save();
        $condition2 = new condition(0, (object)['ruleid' => $rule->get('id'), 'classname' => 'test', 'sortorder' => 1]);
        $condition2->save();

        $actual = $rule->get_condition_records();
        $this->assertCount(2, $actual);

        $this->assertEquals($actual[$condition1->get('id')]->to_record(), $condition1->to_record());
        $this->assertEquals($actual[$condition2->get('id')]->to_record(), $condition2->to_record());
    }

    /**
     * Test cache for condition records.
     */
    public function test_condition_records_get_cached() {
        $this->resetAfterTest();

        $cache = cache::make('tool_dynamic_cohorts', 'conditionrecords');

        $rule = new rule(0, (object)['name' => 'Test rule']);
        $rule->save();
        $key = $rule->get('id');

        $this->assertFalse($cache->get($key));

        $this->assertEmpty($rule->get_condition_records());
        $this->assertIsArray($cache->get($key));
        $this->assertEquals([], $cache->get($key));

        // Saving rule should purge the cache.
        $rule->save();
        $this->assertFalse($cache->get($key));

        $condition1 = new condition(0, (object)['ruleid' => $rule->get('id'), 'classname' => 'test', 'sortorder' => 0]);
        $condition1->save();
        $condition2 = new condition(0, (object)['ruleid' => $rule->get('id'), 'classname' => 'test', 'sortorder' => 1]);
        $condition2->save();

        // Saving conditions should purge the cache.
        $this->assertFalse($cache->get($key));

        $expected = $rule->get_condition_records();

        $this->assertCount(2, $expected);
        $this->assertEquals($expected[$condition1->get('id')]->to_record(), $condition1->to_record());
        $this->assertEquals($expected[$condition2->get('id')]->to_record(), $condition2->to_record());

        $this->assertSame($expected, $cache->get($key));
    }

    /**
     * Test is_broken.
     */
    public function test_is_broken() {
        $this->resetAfterTest();

        $rule = new rule(0, (object)['name' => 'Test rule broken', 'broken' => 1]);
        $this->assertTrue($rule->is_broken());

        $rule = new rule(0, (object)['name' => 'Test not broken']);
        $this->assertFalse($rule->is_broken());
    }

    /**
     * Test is_broken when checking conditions.
     */
    public function test_is_broken_check_conditions() {
        $this->resetAfterTest();

        $rule = new rule(0, (object)['name' => 'Test rule 1']);
        $rule->save();

        $condition = new condition(0, (object) ['ruleid' => $rule->get('id'), 'classname' => 'test', 'sortorder' => 0]);
        $condition->save();

        $this->assertFalse($rule->is_broken());
        $this->assertTrue($rule->is_broken(true));
    }

    /**
     * Test marking a rule broken and unbroken.
     */
    public function test_mark_broken_and_unbroken() {
        $this->resetAfterTest();

        $rule = new rule(0, (object)['name' => 'Test rule 2', 'broken' => 0, 'enabled' => 1]);
        $this->assertFalse($rule->is_broken());
        $this->assertTrue($rule->is_enabled());

        $rule->mark_broken();
        $this->assertTrue($rule->is_broken());
        $this->assertFalse($rule->is_enabled());

        $rule->mark_unbroken();
        $this->assertFalse($rule->is_broken());
        $this->assertFalse($rule->is_enabled());
    }
}
