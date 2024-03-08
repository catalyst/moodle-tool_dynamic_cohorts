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
 * List of Web Services for the plugin.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'tool_dynamic_cohorts_submit_condition_form' => [
        'classname'       => 'tool_dynamic_cohorts\external\condition_form',
        'methodname'      => 'submit',
        'description'     => 'Submits condition form',
        'type'            => 'read',
        'capabilities'    => 'tool/dynamic_cohorts:manage',
        'ajax'            => true,
    ],
    'tool_dynamic_cohorts_get_total_matching_users_for_rule' => [
        'classname'       => 'tool_dynamic_cohorts\external\matching_users',
        'methodname'      => 'get_total',
        'description'     => 'Returns a number of matching users for provided rule ',
        'type'            => 'read',
        'capabilities'    => 'tool/dynamic_cohorts:manage',
        'ajax'            => true,
    ],
    'tool_dynamic_cohorts_get_conditions' => [
        'classname'       => 'tool_dynamic_cohorts\external\rule_conditions',
        'methodname'      => 'get_conditions',
        'description'     => 'Returns a list of conditions for provided rule ',
        'type'            => 'read',
        'capabilities'    => 'tool/dynamic_cohorts:manage',
        'ajax'            => true,
    ],
];
