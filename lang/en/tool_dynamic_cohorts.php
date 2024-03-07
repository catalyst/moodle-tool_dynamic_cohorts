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

$string['addcondition'] = 'Add a condition';
$string['addrule'] = 'Add a new rule';
$string['add_rule'] = 'Add new rule';
$string['bulkprocessing'] = 'Bulk processing';
$string['bulkprocessing_help'] = 'If this option is enabled, users will be added and removed from cohort in bulk. This will significantly improve processing performance. However, using this option will suppress triggering events when users added or removed from cohort.';
$string['cachedef_conditionrecords'] = 'Conditions for a rule';
$string['cachedef_rulesconditions'] = 'Rules with a specific condition';
$string['cohort'] = 'Cohort';
$string['cohortid'] = 'Cohort';
$string['cohortid_help'] = 'A cohort to manage as part of this rule. Only cohorts that are not managed by other plugins are displayed in this list.';
$string['condition'] = 'Condition';
$string['conditions'] = 'Conditions';
$string['conditionchnagesnotapplied'] = 'Condition changes are not applied until you save the rule form';
$string['conditionformtitle'] = 'Rule condition';
$string['condition:cohort_membership'] = 'Cohort membership';
$string['condition:cohort_membership_description'] = 'A user {$a->operator} {$a->cohorts}';
$string['condition:cohort_membership_broken_description'] = 'Condition is broken. Using the same cohort that the given rule is configured to manage to.';
$string['condition:user_profile']  = 'User standard profile field';
$string['condition:user_custom_profile'] = 'User custom profile field';
$string['delete_confirm'] = 'Are you sure you want to delete rule {$a}?';
$string['delete_confirm_condition'] = 'Are you sure you want to delete this condition?';
$string['delete_rule'] = 'Delete rule';
$string['description'] = 'Description';
$string['description_help'] = 'As short description of this rule';
$string['disabled'] = 'Disabled';
$string['disable_confirm'] = 'Are you sure you want to disable rule {$a}?';
$string['dynamic_cohorts:manage'] = 'Manage rules';
$string['edit_rule'] = 'Edit rule';
$string['enabled'] = 'Enabled';
$string['enable_confirm'] = 'Are you sure you want to enable rule {$a}?';
$string['event:conditioncreated'] = 'Condition created';
$string['event:conditiondeleted'] = 'Condition deleted';
$string['event:conditionupdated'] = 'Condition updated';
$string['event:matchingfailed'] = 'Matching users failed';
$string['event:rulecreated'] = 'Rule created';
$string['event:ruleupdated'] = 'Rule updated';
$string['event:ruledeleted'] = 'Rule deleted';
$string['includingmissingdatadesc'] = '(including users with missing data)';
$string['includeusersmissingdata'] = 'include users with missing data';
$string['include_missing_data_help'] = 'Some users may not have a custom field data set yet. This option includes those user in the final result.';
$string['invalidfieldvalue'] = 'Invalid field value';
$string['ismemberof'] = 'is member of';
$string['isnotmemberof'] = 'is not member of';
$string['isnotempty'] = 'is not empty';
$string['managerules'] = 'Manage rules';
$string['managecohorts'] = 'Manage cohorts';
$string['name'] = 'Rule name';
$string['name_help'] = 'A human readable name of this rule.';
$string['operator'] = 'Operator';
$string['or'] = 'OR';
$string['pleaseselectcohort'] = 'Please select a cohort';
$string['pleaseselectfield'] = 'Please select a field';
$string['pluginname'] = 'Dynamic cohort rules';
$string['privacy:metadata:tool_dynamic_cohorts'] = 'Information about rules created or updated by a user';
$string['privacy:metadata:tool_dynamic_cohorts:name'] = 'Rule name';
$string['privacy:metadata:tool_dynamic_cohorts:usermodified'] = 'The ID of the user who created or updated a rule';
$string['privacy:metadata:tool_dynamic_cohorts_c'] = 'Information about conditions created or updated by a user';
$string['privacy:metadata:tool_dynamic_cohorts_c:ruleid'] = 'ID of the rule';
$string['privacy:metadata:tool_dynamic_cohorts_c:usermodified'] = 'The ID of the user who created or updated a condition';
$string['processrulestask'] = 'Process dynamic cohort rules';
$string['profilefield'] = 'Profile field';
$string['ruleisbroken'] = 'Rule is broken';
$string['ruledisabledpleasereview'] = 'Newly created or updated rules are disabled by default. Please review the rule below and enable it when ready.';
$string['ruledeleted'] = 'Rule has been deleted';
$string['ruleenabled'] = 'Rule has been enabled';
$string['ruledisabled'] = 'Rule has been disabled';
$string['rule_entity'] = 'Dynamic cohort rule';
$string['rule_entity.id'] = 'ID';
$string['rule_entity.name'] = 'Name';
$string['rule_entity.description'] = 'Description';
$string['rule_entity.bulkprocessing'] = 'Bulk processing';
$string['rule_entity.status'] = 'Status';
