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

use context_system;
use external_api;
use external_function_parameters;
use external_value;
use tool_dynamic_cohorts\rule;
use invalid_parameter_exception;
use tool_dynamic_cohorts\rule_manager;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/externallib.php');

/**
 * Matching users external APIs.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class matching_users extends external_api {

    /**
     * Describes the parameters for validate_form webservice.
     * @return external_function_parameters
     */
    public static function get_total_parameters(): external_function_parameters {
        return new external_function_parameters([
            'ruleid' => new external_value(PARAM_INT, 'The rule ID to get matching users for'),
        ]);
    }

    /**
     * Gets a total number of matching users for provided rule.
     *
     * @param int $ruleid Rule Id number.
     * @return int
     */
    public static function get_total(int $ruleid): int {
        $params = self::validate_parameters(self::get_total_parameters(), ['ruleid' => $ruleid]);

        self::validate_context(context_system::instance());
        require_capability('tool/dynamic_cohorts:manage', context_system::instance());

        $rule = rule::get_record(['id' => $params['ruleid']]);

        if (empty($rule)) {
            throw new invalid_parameter_exception('Rule does not exist');
        }

        return rule_manager::get_matching_users_count($rule);
    }

    /**
     * Returns description of method result value.
     *
     * @return external_value
     */
    public static function get_total_returns(): external_value {
        return new external_value(PARAM_INT, 'Total number of matching users for provided rule');
    }
}
