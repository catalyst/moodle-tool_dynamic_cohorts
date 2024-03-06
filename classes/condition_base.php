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

namespace tool_dynamic_cohorts;

/**
 * Abstract class that all conditions must extend.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class condition_base {

    /**
     * Condition persistent object.
     *
     * @var condition $condition
     */
    protected $condition;

    /**
     * Protected constructor.
     */
    protected function __construct() {
    }

    /**
     * Return an instance of condition object.
     *
     * @param int $id
     * @param \stdClass|null $record
     * @return \tool_dynamic_cohorts\condition_base|null
     */
    final public static function get_instance(int $id = 0, ?\stdClass $record = null):? condition_base {
        $condition = new condition($id, $record);

        // In case we are getting the instance without underlying persistent data.
        if (!$classname = $condition->get('classname')) {
            $classname = get_called_class();
            $condition->set('classname', $classname);
        }

        if (!class_exists($classname) || !is_subclass_of($classname, self::class)) {
            return null;
        }

        $instance = new $classname();
        $instance->condition = $condition;

        return $instance;
    }

    /**
     * Gets required config data from submitted condition form data.
     *
     * @param \stdClass $formdata Form data generated via $mform->get_data()
     * @return array
     */
    public static function retrieve_config_data(\stdClass $formdata): array {
        $configdata = (array)$formdata;

        // Everything except these fields is considered as config data.
        unset($configdata['id']);
        unset($configdata['ruleid']);
        unset($configdata['position']);

        return $configdata;
    }

    /**
     * A config data for that condition.
     *
     * @return array
     */
    public function get_config_data(): array {
        return json_decode($this->condition->get('configdata'), true);
    }

    /**
     * Sets config data.
     *
     * @param array $configdata
     */
    public function set_config_data(array $configdata): void {
        $this->condition->set('configdata', json_encode($configdata));
    }

    /**
     * Gets condition persistent record.
     *
     * @return condition
     */
    public function get_record(): condition {
        return $this->condition;
    }

    /**
     * Returns a rule record for the given condition.
     *
     * @return rule|null
     */
    public function get_rule(): ?rule {
        return rule::get_record(['id' => $this->get_record()->get('ruleid')]) ?: null;
    }

    /**
     * Gets a list of event classes the condition will be triggered on.
     *
     * @return array
     */
    public function get_events(): array {
        return [];
    }

    /**
     * Human readable description of the broken condition.
     *
     * @return string
     */
    public function get_broken_description(): string {
        return $this->get_record()->get('configdata');
    }

    /**
     * Returns the name of the condition
     *
     * @return string
     */
    abstract public function get_name(): string;

    /**
     * Add condition config form elements.
     *
     * @param \MoodleQuickForm $mform The form to add elements to
     */
    abstract public function config_form_add(\MoodleQuickForm $mform): void;

    /**
     * Validates conditions form elements.
     *
     * @param array $data Form data.
     * @return array Errors array.
     */
    abstract public function config_form_validate(array $data): array;

    /**
     * Returns elements to extend SQL for filtering users.
     * @return condition_sql
     */
    abstract public function get_sql(): condition_sql;

    /**
     * Human-readable description of the configured condition.
     *
     * @return string
     */
    abstract public function get_config_description(): string;

    /**
     * Indicate that condition is broken.
     *
     * @return bool
     */
    abstract public function is_broken(): bool;

}
