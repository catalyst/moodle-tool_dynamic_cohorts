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

use tool_dynamic_cohorts\condition_base;
use tool_dynamic_cohorts\condition_sql;

/**
 * Condition based on quiz result score range.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_result extends condition_base {
    /**
     * Condition name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('condition:quiz_result', 'tool_dynamic_cohorts');
    }

    /**
     * Adds config form elements.
     *
     * @param \MoodleQuickForm $mform
     */
    public function config_form_add(\MoodleQuickForm $mform): void {
        $mform->addElement(
            'autocomplete',
            'quizid',
            get_string('pluginname', 'mod_quiz'),
            $this->get_quiz_options(),
            ['noselectionstring' => get_string('choosedots')]
        );
        $mform->addRule('quizid', null, 'required', null, 'client');
        $mform->setType('quizid', PARAM_INT);

        $mform->addElement('text', 'mingrade', get_string('condition:quiz_result_mingrade', 'tool_dynamic_cohorts'));
        $mform->setType('mingrade', PARAM_FLOAT);
        $mform->setDefault('mingrade', 0);

        $mform->addElement('text', 'maxgrade', get_string('condition:quiz_result_maxgrade', 'tool_dynamic_cohorts'));
        $mform->setType('maxgrade', PARAM_FLOAT);
    }

    /**
     * Validates config form elements.
     *
     * @param array $data Data to validate.
     * @return array
     */
    public function config_form_validate(array $data): array {
        $errors = [];

        if (empty($data['quizid'])) {
            $errors['quizid'] = get_string('required');
            return $errors;
        }

        if (!is_numeric($data['mingrade']) || !is_numeric($data['maxgrade'])) {
            $errors['maxgrade'] = get_string('condition:quiz_result_invalidnumber', 'tool_dynamic_cohorts');
            return $errors;
        }

        $mingrade = (float)$data['mingrade'];
        $maxgrade = (float)$data['maxgrade'];

        if ($mingrade > $maxgrade) {
            $errors['maxgrade'] = get_string('condition:quiz_result_invalidrange', 'tool_dynamic_cohorts');
            return $errors;
        }

        if ($mingrade < 0 || $maxgrade < 0) {
            $errors['maxgrade'] = get_string('condition:quiz_result_nonnegative', 'tool_dynamic_cohorts');
            return $errors;
        }

        global $DB;
        $quiz = $DB->get_record('quiz', ['id' => (int)$data['quizid']], 'id, grade');
        if ($quiz) {
            if ($maxgrade > (float)$quiz->grade) {
                $errors['maxgrade'] = get_string('condition:quiz_result_maxexceedsquiz', 'tool_dynamic_cohorts', $quiz->grade);
            }
        }

        return $errors;
    }

    /**
     * Gets quiz id value.
     *
     * @return int
     */
    protected function get_quizid_value(): int {
        return (int)($this->get_config_data()['quizid'] ?? 0);
    }

    /**
     * Gets min grade value.
     *
     * @return float
     */
    protected function get_mingrade_value(): float {
        return (float)($this->get_config_data()['mingrade'] ?? 0);
    }

    /**
     * Gets max grade value.
     *
     * @return float
     */
    protected function get_maxgrade_value(): float {
        return (float)($this->get_config_data()['maxgrade'] ?? 0);
    }

    /**
     * Human-readable description of the configured condition.
     *
     * @return string
     */
    public function get_config_description(): string {
        global $DB;

        $quizname = $DB->get_field('quiz', 'name', ['id' => $this->get_quizid_value()]);
        if ($quizname === false) {
            $quizname = (string)$this->get_quizid_value();
        }

        return get_string('condition:quiz_result_description', 'tool_dynamic_cohorts', (object)[
            'quiz' => format_string($quizname, true, ['context' => \context_system::instance(), 'escape' => false]),
            'mingrade' => format_float($this->get_mingrade_value(), -1),
            'maxgrade' => format_float($this->get_maxgrade_value(), -1),
        ]);
    }

    /**
     * Human readable description of the broken condition.
     *
     * @return string
     */
    public function get_broken_description(): string {
        global $DB;

        if (!$DB->record_exists('quiz', ['id' => $this->get_quizid_value()])) {
            return get_string('condition:quiz_result_missingquiz', 'tool_dynamic_cohorts');
        }

        if (
            !$DB->record_exists(
                'grade_items',
                [
                    'itemtype' => 'mod',
                    'itemmodule' => 'quiz',
                    'iteminstance' => $this->get_quizid_value(),
                ]
            )
        ) {
            return get_string('condition:quiz_result_missinggradeitem', 'tool_dynamic_cohorts');
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
            $quizgradestable = condition_sql::generate_table_alias();
            $quizidparam = condition_sql::generate_param_alias();
            $mingradeparam = condition_sql::generate_param_alias();
            $maxgradeparam = condition_sql::generate_param_alias();

            $join = "JOIN {quiz_grades} {$quizgradestable} ON ({$quizgradestable}.userid = u.id)";
            $where = "{$quizgradestable}.quiz = :{$quizidparam}
                      AND {$quizgradestable}.grade >= :{$mingradeparam}
                      AND {$quizgradestable}.grade <= :{$maxgradeparam}";
            $params = [
                $quizidparam => $this->get_quizid_value(),
                $mingradeparam => $this->get_mingrade_value(),
                $maxgradeparam => $this->get_maxgrade_value(),
            ];

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
            if ($this->get_quizid_value() <= 0) {
                return true;
            }

            if (!$DB->record_exists('quiz', ['id' => $this->get_quizid_value()])) {
                return true;
            }

            if (
                !$DB->record_exists(
                    'grade_items',
                    [
                        'itemtype' => 'mod',
                        'itemmodule' => 'quiz',
                        'iteminstance' => $this->get_quizid_value(),
                    ]
                )
            ) {
                return true;
            }

            if ($this->get_mingrade_value() < 0 || $this->get_maxgrade_value() < 0) {
                return true;
            }

            if ($this->get_mingrade_value() > $this->get_maxgrade_value()) {
                return true;
            }

            $quizmaxgrade = (float)$DB->get_field('quiz', 'grade', ['id' => $this->get_quizid_value()]);
            if ($this->get_maxgrade_value() > $quizmaxgrade) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gets a list of all quizzes as options.
     *
     * @return array
     */
    protected function get_quiz_options(): array {
        global $DB;

        $options = ['' => get_string('choosedots')];

        $sql = "SELECT q.id, c.fullname AS coursename, q.name
                  FROM {quiz} q
                  JOIN {course} c ON c.id = q.course
              ORDER BY c.fullname, q.name";
        $records = $DB->get_records_sql($sql);

        foreach ($records as $record) {
            $options[$record->id] = format_string($record->coursename, true, ['context' => \context_system::instance()]) .
                ' / ' . format_string($record->name, true, ['context' => \context_system::instance()]);
        }

        return $options;
    }

    /**
     * Gets a list of event classes the condition will be triggered on.
     *
     * @return array
     */
    public function get_events(): array {
        return [
                '\mod_quiz\event\quiz_grade_updated',
        ];
    }
}
