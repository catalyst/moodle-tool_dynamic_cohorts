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
 * List of users matching a rule.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_dynamic_cohorts\rule;
use tool_dynamic_cohorts\cohort_manager;
use core_reportbuilder\system_report_factory;
use tool_dynamic_cohorts\reportbuilder\local\systemreports\matching_users;

require_once(__DIR__.'/../../../config.php');
require_once($CFG->libdir.'/adminlib.php');

$ruleid = required_param('ruleid', PARAM_INT);
$download = optional_param('download', '', PARAM_ALPHA);

admin_externalpage_setup('tool_dynamic_cohorts_rules');

$rule = rule::get_record(['id' => $ruleid]);
if (empty($rule)) {
    throw new dml_missing_record_exception(null);
}

$report = system_report_factory::create(
    matching_users::class,
    context_system::instance(),
    'tool_dynamic_cohorts',
    '',
    0,
    ['ruleid' => $ruleid]
);

$heading = get_string('usersforrule', 'tool_dynamic_cohorts', [
    'rule' => $rule->get('name'),
    'cohort' => cohort_manager::get_cohorts()[$rule->get('cohortid')]->name,
]);
$PAGE->navbar->add($heading);

$indexurl = new moodle_url('/admin/tool/dynamic_cohorts/index.php');

echo $OUTPUT->header();
echo $OUTPUT->heading($heading);
echo $OUTPUT->single_button($indexurl, get_string('backtolistofrules', 'tool_dynamic_cohorts'), 'post', ['primary' => true]);
echo $report->output();
echo $OUTPUT->footer();
