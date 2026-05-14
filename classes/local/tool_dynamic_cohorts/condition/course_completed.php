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
use tool_dynamic_cohorts\condition_base;
use tool_dynamic_cohorts\condition_sql;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/completionlib.php');

/**
 * Condition based on course completion.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_completed extends condition_base {
    /**
     * Operator for any completion date.
     */
    public const OPERATOR_ANY = 1;

    /**
     * Operator for completion dates before some date.
     */
    public const OPERATOR_BEFORE = 2;

    /**
     * Operator for completion dates after some date.
     */
    public const OPERATOR_AFTER = 3;

    /**
     * Condition name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('condition:course_completed', 'tool_dynamic_cohorts');
    }

    /**
     * Gets a list of operators.
     *
     * @return array A list of operators.
     */
    protected function get_operators(): array {
        return [
            self::OPERATOR_ANY => get_string('any', 'tool_dynamic_cohorts'),
            self::OPERATOR_BEFORE => get_string('before', 'tool_dynamic_cohorts'),
            self::OPERATOR_AFTER => get_string('after', 'tool_dynamic_cohorts'),
        ];
    }

    /**
     * Add config form elements.
     *
     * @param \MoodleQuickForm $mform
     */
    public function config_form_add(\MoodleQuickForm $mform): void {
        $mform->addElement('course', 'courseid', get_string('course'), ['onlywithcompletion' => true]);
        $mform->addRule('courseid', null, 'required', null, 'client');
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement(
            'select',
            'operator',
            get_string('completiondate', 'tool_dynamic_cohorts'),
            $this->get_operators()
        );

        $mform->addElement('date_time_selector', 'timecompleted');
        $mform->hideIf('timecompleted', 'operator', 'eq', self::OPERATOR_ANY);
        $mform->setDefault('timecompleted', usergetmidnight(time()));
    }

    /**
     * Validate config form elements.
     *
     * @param array $data Data to validate.
     * @return array
     */
    public function config_form_validate(array $data): array {
        $errors = [];

        if (!isset($data['courseid'])) {
            $errors['courseid'] = get_string('required');
            return $errors;
        }

        return $errors;
    }

    /**
     * Gets configured course ID.
     *
     * @return int
     */
    protected function get_courseid_value(): int {
        return $this->get_config_data()['courseid'] ?? 0;
    }

    /**
     * Gets operator value.
     *
     * @return int
     */
    protected function get_operator_value(): int {
        return $this->get_config_data()['operator'] ?? self::OPERATOR_ANY;
    }

    /**
     * Gets required config data from submitted condition form data.
     *
     * @param \stdClass $formdata Form data generated via $mform->get_data()
     * @return array
     */
    public static function retrieve_config_data(\stdClass $formdata): array {
        $configdata = parent::retrieve_config_data($formdata);

        // When operator is "Any", the timecompleted field is hidden in the form but still submitted
        // with the current timestamp as its default value. Reset it to 0 so it is not used in queries.
        if (isset($configdata['operator']) && (int)$configdata['operator'] === self::OPERATOR_ANY) {
            $configdata['timecompleted'] = 0;
        }

        return $configdata;
    }

    /**
     * Gets configured completion time.
     *
     * @return int
     */
    protected function get_timecompleted_value(): int {
        return $this->get_config_data()['timecompleted'] ?? 0;
    }

    /**
     * Human-readable description of the configured condition.
     *
     * @return string
     */
    public function get_config_description(): string {
        global $DB;

        $coursename = $DB->get_field('course', 'fullname', ['id' => $this->get_courseid_value()]);
        $coursename = format_string($coursename, true, ['context' => \context_system::instance(), 'escape' => false]);

        $operatorvalue = $this->get_operator_value();

        $operator = $operatorvalue != self::OPERATOR_ANY ? strtolower($this->get_operators()[$operatorvalue]) : '';
        $timecompleted = $operatorvalue != self::OPERATOR_ANY ? userdate($this->get_timecompleted_value()) : '';

        return get_string('condition:course_completed_description', 'tool_dynamic_cohorts', (object)[
            'course' => $coursename,
            'operator' => $operator,
            'timecompleted' => $timecompleted,
        ]);
    }

    /**
     * Human readable description of the broken condition.
     *
     * @return string
     */
    public function get_broken_description(): string {
        global $DB;

        // Check course exists.
        if (!$course = $DB->get_record('course', ['id' => $this->get_courseid_value()])) {
            return get_string('missingcourse', 'tool_dynamic_cohorts');
        }

        // Check completion is enabled for a course.
        $completion = new completion_info($course);
        if (!$completion->is_enabled()) {
            return get_string('completionisdisabled', 'tool_dynamic_cohorts');
        }

        return parent::get_broken_description();
    }

    /**
     * Gets SQL data for building SQL.
     *
     * @return condition_sql
     */
    public function get_sql(): condition_sql {
        $sql = new condition_sql('', '1=0', []);

        if (!$this->is_broken()) {
            $params = [];

            $completiontable = condition_sql::generate_table_alias();
            $join = "JOIN {course_completions} $completiontable ON ($completiontable.userid = u.id)";

            $courseid = $this->get_courseid_value();
            $courseidparam = condition_sql::generate_param_alias();
            $params[$courseidparam] = $courseid;

            $timecompleted = $this->get_timecompleted_value();
            $timecompletedparam = condition_sql::generate_param_alias();

            $operator = $this->get_operator_value();

            if ($operator != self::OPERATOR_ANY && $timecompleted > 0) {
                $operator = $operator == self::OPERATOR_BEFORE ? '<' : '>';
                $where = "$completiontable.course = :$courseidparam
                          AND $completiontable.timecompleted $operator :$timecompletedparam";
                $params[$timecompletedparam] = $timecompleted;
            } else {
                $where = "$completiontable.course = :$courseidparam
                          AND $completiontable.timecompleted IS NOT NULL";
            }

            $sql = new condition_sql($join, $where, $params);
        }

        return $sql;
    }

    /**
     * Is condition broken.
     *
     * @return bool
     */
    public function is_broken(): bool {
        global $DB;

        if ($this->get_config_data()) {
            // Check course exists.
            if (!$course = $DB->get_record('course', ['id' => $this->get_courseid_value()])) {
                return true;
            }

            // Check completion is enabled for a course.
            $completion = new completion_info($course);
            if (!$completion->is_enabled()) {
                return true;
            }
        }

        return false;
    }
}
