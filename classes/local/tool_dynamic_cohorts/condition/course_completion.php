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

namespace tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition;

use completion_info;
use core\context\course;
use core\url;
use html_writer;
use tool_dynamic_cohorts\condition_base;
use tool_dynamic_cohorts\condition_sql;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/completionlib.php');

/**
 * Condition based on course completion.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2026 Catalyst IT Australia Pty Ltd
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_completion extends condition_base {
    /**
     * Operator for users who have completed courses.
     */
    public const OPERATOR_HAVE_COMPLETED = 1;

    /**
     * Operator for users who have not completed courses.
     */
    public const OPERATOR_HAVE_NOT_COMPLETED = 2;

    /**
     * Selection operator for any selected course.
     */
    public const SELECTION_ANY = 1;

    /**
     * Selection operator for all selected courses.
     */
    public const SELECTION_ALL = 2;

    /**
     * Period operator for any completion date.
     */
    public const PERIOD_ANY = 1;

    /**
     * Period operator for completion before a date.
     */
    public const PERIOD_BEFORE = 2;

    /**
     * Period operator for completion after a date.
     */
    public const PERIOD_AFTER = 3;

    /**
     * Gets completion operators.
     *
     * @return array
     */
    protected function get_completion_operators(): array {
        return [
            self::OPERATOR_HAVE_COMPLETED => get_string('condition:course_completion:have_completed', 'tool_dynamic_cohorts'),
            self::OPERATOR_HAVE_NOT_COMPLETED => get_string('condition:course_completion:have_not_completed', 'tool_dynamic_cohorts'),
        ];
    }

    /**
     * Gets selection operators.
     *
     * @return array
     */
    protected function get_selection_operators(): array {
        return [
            self::SELECTION_ALL => strtolower(get_string('all')),
            self::SELECTION_ANY => strtolower(get_string('any', 'tool_dynamic_cohorts')),
        ];
    }

    /**
     * Gets period operators.
     *
     * @return array
     */
    protected function get_period_operators(): array {
        return [
            self::PERIOD_ANY => get_string('any', 'tool_dynamic_cohorts'),
            self::PERIOD_BEFORE => get_string('before', 'tool_dynamic_cohorts'),
            self::PERIOD_AFTER => get_string('after', 'tool_dynamic_cohorts'),
        ];
    }

    #[\Override]
    public static function retrieve_config_data(\stdClass $formdata): array {
        $configdata = parent::retrieve_config_data($formdata);

        if ((int) ($configdata['periodoperator'] ?? self::PERIOD_ANY) === self::PERIOD_ANY) {
            $configdata['timecompleted'] = 0;
        }

        return $configdata;
    }

    #[\Override]
    public function config_form_add(\MoodleQuickForm $mform): void {
        $operatorgroup = [];
        $operatorgroup[] = $mform->createElement(
            'select',
            'completionoperator',
            '',
            $this->get_completion_operators()
        );
        $operatorgroup[] = $mform->createElement(
            'select',
            'selectionoperator',
            '',
            $this->get_selection_operators()
        );
        $mform->addGroup($operatorgroup, 'operatorgroup', get_string('users'), ' ', false);

        $mform->addElement(
            'course',
            'courseids',
            get_string('courses'),
            [
                'multiple' => true,
                'onlywithcompletion' => true,
            ]);
        $mform->addRule('courseids', get_string('required'), 'required');

        $mform->addElement(
            'select',
            'periodoperator',
            get_string('completiondate', 'tool_dynamic_cohorts'),
            $this->get_period_operators()
        );

        $mform->addElement('date_time_selector', 'timecompleted', '', ['defaulttime' => usergetmidnight(time())]);
        $mform->hideIf('timecompleted', 'periodoperator', 'eq', self::PERIOD_ANY);
        $mform->setDefault('timecompleted', usergetmidnight(time()));
    }

    #[\Override]
    public function config_form_validate(array $data): array {
        $errors = [];

        if (empty($data['courseids'])) {
            $errors['courseids'] = get_string('required');
        }

        if (!array_key_exists((int) ($data['completionoperator'] ?? 0), $this->get_completion_operators())) {
            $errors['completionoperator'] = get_string('invaliddata');
        }

        if (!array_key_exists((int) ($data['selectionoperator'] ?? 0), $this->get_selection_operators())) {
            $errors['selectionoperator'] = get_string('invaliddata');
        }

        if (!array_key_exists((int) ($data['periodoperator'] ?? 0), $this->get_period_operators())) {
            $errors['periodoperator'] = get_string('invaliddata');
        }

        return $errors;
    }

    #[\Override]
    public function get_config_description(): string {
        global $DB;

        $configuredcourses = $this->get_courseids_value();
        $records = [];
        if (!empty($configuredcourses)) {
            $records = $DB->get_records_list('course', 'id', $configuredcourses, '', 'id, fullname');
        }

        $badges = [];
        foreach ($configuredcourses as $courseid) {
            if (isset($records[$courseid])) {
                $coursename = format_string(
                    $records[$courseid]->fullname,
                    true,
                    [
                        'context' => course::instance($courseid),
                        'escape' => false,
                    ]);
                $courseurl = new url('/course/view.php', ['id' => $courseid]);
                $badges[] = html_writer::link($courseurl, $coursename, [
                    'class' => 'badge badge-secondary',
                    'target' => '_blank',
                    'rel' => 'noopener noreferrer',
                ]);
            } else {
                $badges[] = html_writer::tag('span', $coursename, ['class' => 'badge badge-secondary']);
            }
        }

        $periodoperator = $this->get_period_operator_value();
        $periodclause = '';
        if ($periodoperator !== self::PERIOD_ANY) {
            $periodclause = ' ' . strtolower($this->get_period_operators()[$periodoperator]) . ' ' . userdate($this->get_timecompleted_value());
        }

        return get_string('condition:course_completion:config_description', 'tool_dynamic_cohorts', (object) [
            'completionoperator' => strtolower($this->get_completion_operators()[$this->get_completion_operator_value()]),
            'selectionoperator' => strtolower($this->get_selection_operators()[$this->get_selection_operator_value()]),
            'periodclause' => $periodclause,
            'coursebadges' => implode(' ', $badges),
        ]);
    }

    #[\Override]
    public function get_name(): string {
        return get_string('condition:course_completion:name', 'tool_dynamic_cohorts');
    }

    #[\Override]
    public function get_sql(): condition_sql {
        global $DB;

        $sql = new condition_sql('', '1=0', []);

        if ($this->is_broken()) {
            return $sql;
        }

        $params = [];
        $subtable = condition_sql::generate_table_alias();
        $coursetotalparam = condition_sql::generate_param_alias();
        $params[$coursetotalparam] = count($this->get_courseids_value());

        [$insql, $inparams] = $DB->get_in_or_equal(
            $this->get_courseids_value(),
            SQL_PARAMS_NAMED,
            condition_sql::generate_param_alias()
        );
        $params += $inparams;

        $timewhere = '';
        $periodoperator = $this->get_period_operator_value();
        if ($periodoperator !== self::PERIOD_ANY && $this->get_timecompleted_value() > 0) {
            $timecompletedparam = condition_sql::generate_param_alias();
            $params[$timecompletedparam] = $this->get_timecompleted_value();
            $operator = $periodoperator === self::PERIOD_BEFORE ? '<' : '>';
            $timewhere = "AND cc.timecompleted $operator :$timecompletedparam";
        }

        $join = "LEFT JOIN (
                    SELECT cc.userid, COUNT(DISTINCT cc.course) AS completedcount
                      FROM {course_completions} cc
                     WHERE cc.course $insql
                           AND cc.timecompleted IS NOT NULL
                           $timewhere
                  GROUP BY cc.userid
                ) $subtable ON $subtable.userid = u.id";

        $completionoperator = $this->get_completion_operator_value();
        $selectionoperator = $this->get_selection_operator_value();

        if ($completionoperator === self::OPERATOR_HAVE_COMPLETED) {
            $where = $selectionoperator === self::SELECTION_ALL
                ? "COALESCE($subtable.completedcount, 0) = :$coursetotalparam"
                : "COALESCE($subtable.completedcount, 0) >= 1";
        } else {
            $where = $selectionoperator === self::SELECTION_ALL
                ? "COALESCE($subtable.completedcount, 0) < :$coursetotalparam"
                : "COALESCE($subtable.completedcount, 0) = 0";
        }

        return new condition_sql($join, $where, $params);
    }

    #[\Override]
    public function is_broken(): bool {
        global $DB;

        $data = $this->get_config_data();
        if (empty($data)) {
            return false;
        }

        if (!array_key_exists($this->get_completion_operator_value(), $this->get_completion_operators())) {
            return true;
        }

        if (!array_key_exists($this->get_selection_operator_value(), $this->get_selection_operators())) {
            return true;
        }

        if (!array_key_exists($this->get_period_operator_value(), $this->get_period_operators())) {
            return true;
        }

        if (empty($this->get_courseids_value())) {
            return true;
        }

        foreach ($this->get_courseids_value() as $courseid) {
            $course = $DB->get_record('course', ['id' => $courseid]);
            if (!$course) {
                return true;
            }

            $completion = new completion_info($course);
            if (!$completion->is_enabled()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Human readable description of the broken condition.
     *
     * @return string
     */
    public function get_broken_description(): string {
        global $DB;

        foreach ($this->get_courseids_value() as $courseid) {
            $course = $DB->get_record('course', ['id' => $courseid]);
            if (!$course) {
                return get_string('missingcourse', 'tool_dynamic_cohorts');
            }

            $completion = new completion_info($course);
            if (!$completion->is_enabled()) {
                return get_string('completionisdisabled', 'tool_dynamic_cohorts');
            }
        }

        return parent::get_broken_description();
    }

    /**
     * Gets completion operator value.
     *
     * @return int
     */
    protected function get_completion_operator_value(): int {
        return (int) ($this->get_config_data()['completionoperator'] ?? self::OPERATOR_HAVE_COMPLETED);
    }

    /**
     * Gets selection operator value.
     *
     * @return int
     */
    protected function get_selection_operator_value(): int {
        return (int) ($this->get_config_data()['selectionoperator'] ?? self::SELECTION_ANY);
    }

    /**
     * Gets period operator value.
     *
     * @return int
     */
    protected function get_period_operator_value(): int {
        return (int) ($this->get_config_data()['periodoperator'] ?? self::PERIOD_ANY);
    }

    /**
     * Gets configured course ids.
     *
     * @return int[]
     */
    protected function get_courseids_value(): array {
        return array_values(array_unique(array_map('intval', $this->get_config_data()['courseids'] ?? [])));
    }

    /**
     * Gets configured completion time.
     *
     * @return int
     */
    protected function get_timecompleted_value(): int {
        return (int) ($this->get_config_data()['timecompleted'] ?? 0);
    }

    /**
     * A list of events the condition is listening to.
     *
     * @return string[]
     */
    public function get_events(): array {
        return [
            '\\core\\event\\course_completed',
            '\\core\\event\\course_completion_updated',
        ];
    }
}
