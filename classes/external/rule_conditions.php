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
use external_multiple_structure;
use external_single_structure;
use tool_dynamic_cohorts\condition_base;
use tool_dynamic_cohorts\rule;
use invalid_parameter_exception;

/**
 * Rule conditions external APIs.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rule_conditions extends external_api {

    /**
     * Describes the parameters for validate_form webservice.
     *
     * @return external_function_parameters
     */
    public static function get_conditions_parameters(): external_function_parameters {
        return new external_function_parameters([
            'ruleid' => new external_value(PARAM_INT, 'Rule ID to get conditions for'),
        ]);
    }

    /**
     * Gets get a list of conditions for provided rule.
     *
     * @param int $ruleid Rule Id number.
     * @return array
     */
    public static function get_conditions(int $ruleid): array {
        $params = self::validate_parameters(self::get_conditions_parameters(), ['ruleid' => $ruleid]);

        self::validate_context(context_system::instance());
        require_capability('tool/dynamic_cohorts:manage', context_system::instance());

        $rule = rule::get_record(['id' => $params['ruleid']]);

        if (empty($rule)) {
            throw new invalid_parameter_exception('Rule does not exist');
        }

        $conditions = [];

        foreach ($rule->get_condition_records() as $condition) {
            $instance = condition_base::get_instance(0, $condition->to_record());

            if (!$instance) {
                $name = $condition->get('classname');
                $description = $condition->get('configdata');
                $configdata = $condition->get('configdata');
            } else {
                $name = $instance->get_name();
                $description = $instance->is_broken() ? $instance->get_broken_description() : $instance->get_config_description();
                $configdata = json_encode($instance->get_config_data());
            }

            $conditions[$condition->get('id')] = [
                'id' => (int)$condition->get('id'),
                'sortorder' => (int)$condition->get('sortorder'),
                'classname' => $condition->get('classname'),
                'configdata' => $configdata,
                'description' => $description,
                'name' => $name,
            ];
        }

        return $conditions;
    }

    /**
     * Returns description of method result value.
     *
     * @return external_multiple_structure
     */
    public static function get_conditions_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_INT, ''),
                'sortorder' => new external_value(PARAM_INT, ''),
                'classname' => new external_value(PARAM_RAW, ''),
                'configdata' => new external_value(PARAM_RAW, ''),
                'description' => new external_value(PARAM_RAW, ''),
                'name' => new external_value(PARAM_RAW, ''),
            ])
        );
    }
}
