<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Callbacks.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_dynamic_cohorts\condition_form;

/**
 * A new condition form as a fragment.
 *
 * @param array $args List of named arguments for the fragment loader.
 * @return string
 */
function tool_dynamic_cohorts_output_fragment_condition_form(array $args): string {
    $args = (object) $args;

    $classname = clean_param($args->classname, PARAM_RAW);

    $ajaxdata = [];
    if (!empty($args->jsonformdata)) {
        $serialiseddata = json_decode($args->jsonformdata);
        parse_str($serialiseddata, $ajaxdata);
    }

    $mform = new condition_form(null, ['classname' => $classname], 'post', '', null, true, $ajaxdata);

    unset($ajaxdata['classname']);

    if (!empty($args->defaults)) {
        $data = json_decode($args->defaults, true);
        if (!empty($data)) {
            $confifdata = json_decode($data['configdata']);
            $data = $data + (array)$confifdata;
            $mform->set_data($data);
        }
    }

    if (!empty($ajaxdata)) {
        $mform->is_validated();
    }

    return $mform->render();
}
