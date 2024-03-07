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

namespace tool_dynamic_cohorts\task;

use core\task\manager;
use core\task\scheduled_task;
use tool_dynamic_cohorts\rule;

/**
 * Processing rules.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_rules extends scheduled_task {

    /**
     * Task name.
     */
    public function get_name() {
        return get_string('processrulestask', 'tool_dynamic_cohorts');
    }

    /**
     * Task execution.
     */
    public function execute() {
        $rules = rule::get_records(['enabled' => 1, 'broken' => 0], 'id');

        foreach ($rules as $rule) {
            $adhoctask = new process_rule();
            $adhoctask->set_custom_data($rule->get('id'));
            $adhoctask->set_component('tool_dynamic_cohorts');

            manager::queue_adhoc_task($adhoctask, true);
        }
    }
}
