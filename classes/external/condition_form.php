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
use moodle_exception;
use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use tool_dynamic_cohorts\condition_base;
use tool_dynamic_cohorts\condition_form as form;

/**
 * Condition form AJAX submission.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class condition_form extends external_api {

    /**
     * Describes the parameters for validate_form webservice.
     * @return external_function_parameters
     */
    public static function submit_parameters(): external_function_parameters {
        return new external_function_parameters([
            'classname' => new external_value(PARAM_RAW, 'The condition class being submitted'),
            'jsonformdata' => new external_value(PARAM_RAW, 'The data from the form, encoded as a json array'),
        ]);
    }

    /**
     * Submits a form via AJAX.
     *
     * @param string $classname Condition class name.
     * @param string $jsonformdata The data from the form, encoded as a json array.
     * @return array
     */
    public static function submit(string $classname, string $jsonformdata): array {
        $params = self::validate_parameters(self::submit_parameters(),
            ['classname' => $classname, 'jsonformdata' => $jsonformdata]);

        // Always in a system context.
        self::validate_context(context_system::instance());
        require_capability('tool/dynamic_cohorts:manage', context_system::instance());

        $ajaxdata = [];
        if (!empty($params['jsonformdata'])) {
            $serialiseddata = json_decode($params['jsonformdata']);
            parse_str($serialiseddata, $ajaxdata);
        }

        $mform = new form(null, ['classname' => $classname], 'post', '', null, true, $ajaxdata);
        if (!$mform->is_validated()) {
            throw new moodle_exception('invaliddata');
        }

        $formdata = $mform->get_data();

        $condition = condition_base::get_instance((int)$formdata->id, (object)['classname' => $classname]);
        $condition->set_config_data($condition::retrieve_config_data($formdata));

        return [
            'id' => (int)$formdata->id,
            'sortorder' => (int)$formdata->sortorder,
            'classname' => $classname,
            'configdata' => json_encode($condition->get_config_data()),
            'description' => $condition->is_broken() ? $condition->get_broken_description() : $condition->get_config_description(),
            'name' => $condition->get_name(),
        ];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function submit_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, ''),
            'sortorder' => new external_value(PARAM_INT, ''),
            'classname' => new external_value(PARAM_RAW, ''),
            'configdata' => new external_value(PARAM_RAW, ''),
            'description' => new external_value(PARAM_RAW, ''),
            'name' => new external_value(PARAM_RAW, ''),
        ]);
    }

}
