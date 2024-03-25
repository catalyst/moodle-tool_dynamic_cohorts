<a href="https://github.com/catalyst/moodle-tool_dynamic_cohorts/actions?query=branch%3AMOODLE_401_STABLE">
<img src="https://github.com/catalyst/moodle-tool_dynamic_cohorts/actions/workflows/ci.yml/badge.svg?branch=3AMOODLE_401_STABLE" >
</a>

# Dynamic cohort rules #

Dynamic cohort rules plugin can dynamically add and remove users from cohorts based on rules, which themselves are composed of conditions.

# Versions and branches

| Moodle Version | Branch            | 
|----------------|-------------------|
| Moodle 4.1+    | MOODLE_401_STABLE | 

## Installing via uploaded ZIP file ##

1. Log in to your Moodle site as an admin and go to _Site administration >
   Plugins > Install plugins_.
2. Upload the ZIP file with the plugin code. You should only be prompted to add
   extra details if your plugin type is not automatically detected.
3. Check the plugin validation report and finish the installation.

## Installing manually ##

The plugin can be also installed by putting the contents of this directory to

    {your/moodle/dirroot}/admin/tool/dynamic_cohorts

Afterwards, log in to your Moodle site as an admin and go to _Site administration >
Notifications_ to complete the installation.

Alternatively, you can run

    $ php admin/cli/upgrade.php

to complete the installation from the command line.


# How does it work?

There are two concepts, rules and conditions.

## Conditions

Conditions are simple predicates which assert something about a user in the system. Any plugin may specify a condition. As an example conditions related to user profile fields. These can be used to match users based on the value of a profile field.

### Built-in conditions

* Authentication method (Manual, SAML and etc)
* Cohort fields (if a user is a member of cohort(s) matching specific cohort fields like cohort name, context, custom fields and etc)
* Cohort membership (if a user is a member of cohort(s)).
* User standard profile fields (e.g. first name, last name, username, auth method and etc).
* User custom profile fields (text and menu types are supported).

## Rules

Rules are what determine if a user will be added or removed from a cohort. A rule is defined by few things:

1. A cohort
2. A set of conditions
3. A logical operator to be applied for conditions (OR/AND)

For users to be added to the cohort specified by a rule, they must match all of rule's conditions (logical operator AND) or any of rule's conditions (logical operator OR). 

**NB:** A cohort can be managed by _one and only one_ rule. This is to prevent rules competing over users in a cohort (e.g., to avoid situations where Rule A wants users a, b, c to be in a cohort, but Rule B wants to remove user c from the same cohort). 

Rules can be processed by two mechanisms:

1. By cron: When a rule is created or updated, there may be many users that need to be added or removed from a cohort. This process is handled by cron, and depending on how many users are matched by a rule, this process can take some time. For rules matching large sets of users, some [configuration options](#rule-processing-options) are provided which may be useful to server administrators.
2. By event: rules may also listen to certain events. When one of these events triggers, appropriate rules will be checked and the user will be added to the appropriate cohort immediately. For example, the User standard profile field rule listens to the "User created" and "User updated" events.

# Configuration

## Prerequisites
1. At least one manually created cohort (See _Site administration > Users > Cohorts_)

## Creating a rule
1. Navigate to _Site administration > Users > Accounts > Dynamic cohort rules > Manage rules_
2. Press the "Add a new rule" button
3. From this form you can specify the cohort users will be added to, and add any conditions available in your system. As metioned above, a user must match **all** conditions to be added to the cohort
4. Press the "Save changes" button

You will be directed to the manage rules page where you can review your rule before enabling it by clicking the eye.

**Note:** It is important to review your new rule to make sure it is configured properly. Consider carefully how many users are affected by the rule (displayed in the table) before enabling it. For rules operating on large sets of users see [rule processing options](#rule-processing-options)

Any subsequent edits to a rule will disable it and require you to review and re-enable it.

## Rule processing options
Rules are processed regularly by cron; by default cron will add each user to the specified cohort one by one. For large sets of users, this can take a long time and potentially block other cron tasks.

To mitigate this, an option is provided to process "in bulk" instead of adding them to a cohort one at a time. To enable it for a rule:

1. Go to the rule edit table 
2. Check the "Bulk processing" checkbox
3. Save the rule and review it before enabling

# Technical details

## Condition implementation

### Quick start

Any plugin can easily implement a condition by adding classes in the `\local\tool_dynamic_cohorts\condition` namespace. Each condition must extend the [base_condition class](classes/condition_base.php). As an example, the itself provides some conditions; the directory structure is as follows:

```
tool_dynamic_cohorts
└── classes
    └── local
       └── tool_dynamic_cohorts
           └── condition
               ├── auth_method.php
               ├── cohort_membership.php
               ├── user_custom_profile.php
               └── user_profile.php
```

See [cohort_membership.php](classes/local/tool_dynamic_cohorts/condition/cohort_membership.php) for an example implementation of a single rule. Any plugin wishing to add a rule must simply add similar class inside its own directory. tool_dynamic_cohorts will automatically discover new rules.  

### Triggering on event

Any condition can specify a list of events to listen to by overriding the `get_events` method. Simply return a list of events. See [user_custom_profile.php](classes/local/tool_dynamic_cohorts/condition/user_custom_profile.php) for an example.

## Task processing
Task processing is orchestrated by a simple mechanism:

1. A scheduled task runs periodically which gets all enabled rules, then queues an ad-hoc task to process the rule (i.e., one ad-hoc task per rule)
2. The ad-hoc task will either:
   a) Iterate over all users, adding them to the cohort one at a time (via the cohort API)
   b) If bulk processing is enabled, users will be inserted directly in to the DB in batches, bypassing the core API

## License ##

2024 Catalyst IT

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program.  If not, see <https://www.gnu.org/licenses/>.
