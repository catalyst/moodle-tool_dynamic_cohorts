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

use core\task\adhoc_task;
use tool_dynamic_cohorts\rule_manager;
use tool_dynamic_cohorts\rule;

/**
 * Processing a single rule.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_rule extends adhoc_task {

    /**
     * Task execution
     */
    public function execute() {
        $ruleid = $this->get_custom_data();

        try {
            $rule = rule::get_record(['id' => $ruleid]);
        } catch (\Exception $e) {
            mtrace("Processing dynamic cohort rules: rule with ID  {$ruleid} is not found.");
            return;
        }

        mtrace("Processing dynamic cohort rules: processing rule with id  {$ruleid}");
        rule_manager::process_rule($rule);
    }
}
