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

/**
 * Rules edit page.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\notification;
use tool_dynamic_cohorts\rule;
use tool_dynamic_cohorts\rule_form;
use tool_dynamic_cohorts\rule_manager;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$ruleid = optional_param('ruleid', 0, PARAM_INT);
$action = !empty($ruleid) ? 'edit' : 'add';

admin_externalpage_setup('tool_dynamic_cohorts_rules');

$manageurl = new moodle_url('/admin/tool/dynamic_cohorts/index.php');
$editurl = new moodle_url('/admin/tool/dynamic_cohorts/edit.php');
$header = get_string($action . '_rule', 'tool_dynamic_cohorts');
$PAGE->navbar->add($header);


if (!empty($ruleid)) {
    $rule = rule::get_record(['id' => $ruleid]);
    if (empty($rule)) {
        throw new dml_missing_record_exception(null);
    } else {
        $defaultcohort = $DB->get_record('cohort', ['id' => $rule->get('cohortid')]);
        $mform = new rule_form(rule_manager::build_edit_url($rule)->out(), ['defaultcohort' => $defaultcohort ?: null]);
        $mform->set_data(rule_manager::build_data_for_form($rule));
    }
} else {
    $mform = new rule_form();
}

if ($mform->is_cancelled()) {
    redirect($manageurl);
} else if ($formdata = $mform->get_data()) {
    try {
        rule_manager::process_form($formdata);
        notification::success(get_string('changessaved'));
        notification::warning(get_string('ruledisabledpleasereview', 'tool_dynamic_cohorts'));
    } catch (Exception $e) {
        notification::error($e->getMessage());
    }
    redirect($manageurl);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($header);
$mform->display();
echo $OUTPUT->footer();
