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
 * Plugin strings are defined here.
 *
 * @package     tool_dynamic_cohorts
 * @category    string
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['add_rule'] = 'Add new rule';
$string['addcondition'] = 'Add a condition';
$string['addrule'] = 'Add a new rule';
$string['after'] = 'After';
$string['any'] = 'Any';
$string['before'] = 'Before';
$string['broken'] = 'Broken';
$string['brokenruleswarning'] = 'There are some broken rules require your attention.  <br /> To fix a broken rule you should remove all broken conditions. <br />Sometimes a rule becomes broken when matching users SQL failed. In this case all condition are ok, but the rule is marked as broken. You should check Moodle logs for "Matching users failed" event and related SQL errors. <br />Please note, that in any case you have to re-save the rule to mark it as unbroken.';
$string['bulkprocessing'] = 'Bulk processing';
$string['bulkprocessing_help'] = 'If this option is enabled, users will be added and removed from cohort in bulk. This will significantly improve processing performance. However, using this option will suppress triggering events when users added or removed from cohort.';
$string['cachedef_conditionrecords'] = 'Conditions for a rule';
$string['cachedef_matchinguserscount'] = 'Matching users count for a rule';
$string['cachedef_rulesconditions'] = 'Rules with a specific condition';
$string['cannotenablebrokenrule'] = 'A broken rule can\'t be enabled';
$string['cf_include_missing_data'] = 'Include cohorts with missing data.';
$string['cf_include_missing_data_help'] = 'Cohorts may not have a custom field data set yet. This option includes those cohorts in the final result.';
$string['cf_includingmissingdatadesc'] = '(including cohorts with missing data)';
$string['cohort'] = 'Cohort';
$string['cohortid'] = 'Cohort';
$string['cohortid_help'] = 'A cohort to manage as part of this rule. Only cohorts that are not managed by other plugins are displayed in this list.';
$string['cohortswith'] = 'Cohort(s) with field';
$string['completed:add'] = 'Rule has been added';
$string['completed:delete'] = 'Rule has been deleted';
$string['completed:disable'] = 'Rule has been disabled';
$string['completed:enable'] = 'Rule has been enabled';
$string['completed:update'] = 'Rule has been updated';
$string['completiondate'] = 'Completion date';
$string['completionisdisabled'] = 'Completion is disabled for configured course';
$string['condition'] = 'Condition';
$string['condition:auth_method'] = 'Authentication method';
$string['condition:cohort_field'] = 'Cohort field';
$string['condition:cohort_field_description'] = 'Users who {$a->operator} cohorts with field \'{$a->field}\' {$a->fieldoperator} {$a->fieldvalue}';
$string['condition:cohort_membership'] = 'Cohort membership';
$string['condition:cohort_membership_broken_description'] = 'Condition is broken. Using the same cohort that the given rule is configured to manage to.';
$string['condition:cohort_membership_description'] = 'Users who {$a->operator} {$a->cohorts}';
$string['condition:course_completed'] = 'Course completed';
$string['condition:course_completed_description'] = 'Users who have completed course "{$a->course}" {$a->operator} {$a->timecompleted}';
$string['condition:course_not_completed'] = 'Course not completed';
$string['condition:course_not_completed_description'] = 'Users who have not completed course "{$a->course}"';
$string['condition:quiz_result'] = 'Quiz result';
$string['condition:quiz_result_description'] = 'Users with quiz "{$a->quiz}" score from {$a->mingrade} to {$a->maxgrade}';
$string['condition:quiz_result_maxexceedsquiz'] = 'Maximum score cannot be greater than quiz max grade ({$a}).';
$string['condition:quiz_result_maxgrade'] = 'Maximum score';
$string['condition:quiz_result_mingrade'] = 'Minimum score';
$string['condition:quiz_result_missinggradeitem'] = 'Missing quiz grade item';
$string['condition:quiz_result_missingquiz'] = 'Missing quiz';
$string['condition:quiz_result_invalidnumber'] = 'Minimum and maximum scores must be numeric.';
$string['condition:quiz_result_invalidrange'] = 'Minimum score must be less than or equal to maximum score.';
$string['condition:quiz_result_nonnegative'] = 'Minimum and maximum scores must be zero or greater.';
$string['condition:profile_field_description'] = 'Users with {$a->field} {$a->fieldoperator} {$a->fieldvalue}';
$string['condition:user_created'] = 'User created time';
$string['condition:user_custom_profile'] = 'User custom profile field';
$string['condition:user_enrolment'] = 'User enrolment';
$string['condition:user_enrolment_description'] = 'Users who are {$a->operator} into course "{$a->coursename}" (id {$a->courseid}) with "{$a->role}" role using "{$a->enrolmethod}" enrolment method';
$string['condition:user_last_login'] = 'User last login';
$string['condition:user_profile'] = 'User standard profile field';
$string['condition:user_profile_interests'] = 'User interests';
$string['condition:user_profile_interests_description'] = 'Users with interests containing the following tags {$a}';
$string['condition:user_profile_interests_description_not'] = 'Users with interests not containing the following tags {$a}';
$string['condition:user_role'] = 'User role';
$string['condition:user_role_description_category'] = 'Users who {$a->operator} "{$a->role}" in category {$a->categoryname} (id {$a->categoryid})';
$string['condition:user_role_description_course'] = 'Users who {$a->operator} "{$a->role}" in course {$a->coursename} (id {$a->courseid})';
$string['condition:user_role_description_system'] = 'Users who {$a->operator} "{$a->role}" in system context';
$string['conditionchnagesnotapplied'] = 'Condition changes are not applied until you save the rule form';
$string['conditionformtitle'] = 'Rule condition';
$string['conditions'] = 'Conditions';
$string['conditionsformtitle'] = 'Rule conditions';
$string['conditionstext'] = '{$a->conditions} ( logical {$a->operator} )';
$string['delete_confirm'] = 'Are you sure you want to delete rule?';
$string['delete_confirm_condition'] = 'Are you sure you want to delete this condition?';
$string['delete_rule'] = 'Delete rule';
$string['description'] = 'Description';
$string['description_help'] = 'As short description of this rule';
$string['disable_confirm'] = 'Are you sure you want to disable rule?';
$string['disabled'] = 'Disabled';
$string['donothaverole'] = 'do not have role';
$string['dynamic_cohorts:manage'] = 'Manage rules';
$string['edit_rule'] = 'Edit rule';
$string['enable_confirm'] = 'Are you sure you want to enable rule?';
$string['enabled'] = 'Enabled';
$string['enrolled'] = 'Enrolled';
$string['enrolmethod'] = 'Enrolment method';
$string['event:conditioncreated'] = 'Condition created';
$string['event:conditiondeleted'] = 'Condition deleted';
$string['event:conditionupdated'] = 'Condition updated';
$string['event:matchingfailed'] = 'Matching users failed';
$string['event:rulecreated'] = 'Rule created';
$string['event:ruledeleted'] = 'Rule deleted';
$string['event:ruleupdated'] = 'Rule updated';
$string['ever'] = 'Ever';
$string['everloggedin'] = 'Users who have logged in at least once';
$string['haverole'] = 'have role';
$string['include_missing_data'] = 'Include users with missing data.';
$string['include_missing_data_help'] = 'Some users may not have a custom field data set yet. This option includes those user in the final result.';
$string['includechildren'] = 'including children (categories and courses)';
$string['includeusersmissingdata'] = 'include users with missing data';
$string['includingmissingdatadesc'] = '(including users with missing data)';
$string['inlast'] = 'In the last';
$string['inlastloggedin'] = 'Users who have logged in in the last {$a}';
$string['inthefuture'] = 'is in the future';
$string['inthepast'] = 'is in the past';
$string['invalidfieldvalue'] = 'Invalid field value';
$string['isafter'] = 'is after';
$string['isbefore'] = 'is before';
$string['ismemberof'] = 'are members of';
$string['isnotempty'] = 'is not empty';
$string['isnotmemberof'] = 'are not members of';
$string['loggedintime'] = 'Users who logged in {$a->operator} {$a->time}';
$string['logical_operator'] = 'Logical operator';
$string['logical_operator_help'] = 'A logical operator to be applied to conditions for this rule. Operator "AND" means a user has to match all conditions to be added to a cohort. "OR" means a user has to match any of conditions to be added to a cohort.';
$string['managecohorts'] = 'Manage cohorts';
$string['managerules'] = 'Manage rules';
$string['matchingusers'] = 'Matching users';
$string['missingcourse'] = 'Missing course';
$string['missingcoursecat'] = 'Missing course category';
$string['missingenrolmentmethod'] = 'Missing enrolment method {$a}';
$string['missingrole'] = 'Missing role';
$string['name'] = 'Rule name';
$string['name_help'] = 'A human readable name of this rule.';
$string['never'] = 'Never';
$string['neverloggedin'] = 'Users who have never logged in';
$string['notenrolled'] = 'Not enrolled';
$string['operator'] = 'Operator';
$string['or'] = 'OR';
$string['pleaseselectcohort'] = 'Please select a cohort';
$string['pleaseselectfield'] = 'Please select a field';
$string['pluginname'] = 'Dynamic cohorts';
$string['privacy:metadata:tool_dynamic_cohorts'] = 'Information about rules created or updated by a user';
$string['privacy:metadata:tool_dynamic_cohorts:name'] = 'Rule name';
$string['privacy:metadata:tool_dynamic_cohorts:usermodified'] = 'The ID of the user who created or updated a rule';
$string['privacy:metadata:tool_dynamic_cohorts_c'] = 'Information about conditions created or updated by a user';
$string['privacy:metadata:tool_dynamic_cohorts_c:ruleid'] = 'ID of the rule';
$string['privacy:metadata:tool_dynamic_cohorts_c:usermodified'] = 'The ID of the user who created or updated a condition';
$string['processrulestask'] = 'Process dynamic cohort rules';
$string['profilefield'] = 'Profile field';
$string['realtime'] = 'Real time processing';
$string['realtime_help'] = 'If enabled, the rule will be processed synchronously as part of the event (if conditions support triggering on the event). Use caution when enabling as long running rule processing will block the user interface.';
$string['realtimedisabledglobally'] = 'Realtime processing disabled globally';
$string['rule_entity'] = 'Dynamic cohort rule';
$string['rule_entity.bulkprocessing'] = 'Bulk processing';
$string['rule_entity.description'] = 'Description';
$string['rule_entity.id'] = 'ID';
$string['rule_entity.name'] = 'Name';
$string['rule_entity.realtime'] = 'Realtime processing';
$string['rule_entity.status'] = 'Status';
$string['ruledeleted'] = 'Rule has been deleted';
$string['ruledisabled'] = 'Rule has been disabled';
$string['ruledisabledpleasereview'] = 'Newly created or updated rules are disabled by default. Please review the rule below and enable it when ready.';
$string['ruleenabled'] = 'Rule has been enabled';
$string['settings:realtime'] = 'Real time processing';
$string['settings:realtime_desc'] = 'When enabled, rules with conditions that support triggering on the event will be processed synchronously as part of the event. Use caution when enabling as long running rule processing will block the user interface.';
$string['settings:releasemembers'] = 'Release members';
$string['settings:releasemembers_desc'] = 'If enabled all members will be removed from a cohort once it\'s not managed by the plugin (e.g a rule is deleted or cohort for a rule is changed). <br/> Please note: no cohort_member_removed events will be triggered when members are released from a cohort. Otherwise, the rule will be processed via cron.';
$string['usercreated'] = 'User was created';
$string['usercreatedin'] = 'Users who were created in the last {$a}';
$string['usercreatedtime'] = 'Users who were created {$a->operator} {$a->time}';
$string['userlastlogin'] = 'User\'s last login';
$string['usersforrule'] = 'Users matching rule "{$a->rule}" for cohort "{$a->cohort}"';
