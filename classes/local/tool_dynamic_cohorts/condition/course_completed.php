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
use tool_dynamic_cohorts\rule_manager;

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
     * Whether this condition can be merged with other course_completed conditions.
     *
     * Only OPERATOR_ANY conditions can be merged; date-bounded conditions carry
     * per-course timestamps that must remain separate.
     *
     * @return bool
     */
    public function can_merge(): bool {
        return !$this->is_broken() && $this->get_operator_value() === self::OPERATOR_ANY;
    }

    /**
     * Build a single EXISTS subquery covering all merged OPERATOR_ANY instances.
     *
     * Replaces N separate JOINs (which produce an N-way cross-product) with one
     * correlated EXISTS containing an IN() list. Only called for OR rules.
     *
     * @param static[] $instances Array of course_completed instances to merge.
     * @param int $operator Rule logical operator.
     * @return condition_sql
     */
    public static function build_merged_sql(array $instances, int $operator): condition_sql {
        global $DB;

        if ($operator != rule_manager::CONDITIONS_OPERATOR_OR || empty($instances)) {
            return new condition_sql('', '1=0', []);
        }

        $courseids = array_map(fn($instance) => $instance->get_courseid_value(), array_values($instances));
        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, condition_sql::generate_param_alias());

        $where = "EXISTS (SELECT 1 FROM {course_completions}"
            . " WHERE userid = u.id AND course $insql AND timecompleted IS NOT NULL)";

        return new condition_sql('', $where, $params);
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
