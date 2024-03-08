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

namespace tool_dynamic_cohorts\reportbuilder\local\systemreports;

use context;
use context_system;
use core_reportbuilder\local\entities\user;
use core_reportbuilder\system_report;
use tool_dynamic_cohorts\condition_manager;
use tool_dynamic_cohorts\rule;

/**
 * List of user matching a rule.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class matching_users extends system_report {

    /**
     * Initialise the report.
     *
     * @return void
     */
    protected function initialise(): void {
        $ruleid = $this->get_parameter('ruleid', 0, PARAM_INT);
        $rule = rule::get_record(['id' => $ruleid], MUST_EXIST);

        $userentity = new user();
        $usertablealias = $userentity->get_table_alias('user');
        $this->set_main_table('user', $usertablealias);
        $this->add_entity($userentity);

        $conditions = $rule->get_condition_records();
        if (empty($conditions) || $rule->is_broken()) {
            // No conditions. Filter out all users.
            $this->add_base_condition_sql(' true = false');
        }

        $sql = condition_manager::build_sql_data($conditions);

        $this->add_join($sql->get_join());
        $this->add_base_condition_sql($sql->get_where(), $sql->get_params());

        $this->add_column_from_entity('user:fullnamewithlink');
        $this->add_column_from_entity('user:email');
        $this->add_column_from_entity('user:idnumber');

        $this->set_initial_sort_column('user:fullnamewithlink', SORT_ASC);

        $this->add_filter_from_entity('user:fullname');
        $this->add_filter_from_entity('user:email');
        $this->add_filter_from_entity('user:idnumber');

        $this->set_downloadable(true);
    }

    /**
     * Returns report context.
     *
     * @return \context
     */
    public function get_context(): context {
        return context_system::instance();
    }

    /**
     * Check if can view this system report.
     *
     * @return bool
     */
    protected function can_view(): bool {
        return has_capability('tool/dynamic_cohorts:manage', $this->get_context());
    }
}
