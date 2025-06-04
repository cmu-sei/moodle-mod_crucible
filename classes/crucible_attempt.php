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

namespace mod_crucible;

/**
 * crucible Attempt wrapper class to encapsulate functions needed to individual
 * attempt records
 *
 * @package     mod_crucible
 * @copyright   2020 Carnegie Mellon Univeristy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/*
Crucible Plugin for Moodle
Copyright 2020 Carnegie Mellon University.
NO WARRANTY. THIS CARNEGIE MELLON UNIVERSITY AND SOFTWARE ENGINEERING INSTITUTE MATERIAL IS FURNISHED ON AN "AS-IS" BASIS.
CARNEGIE MELLON UNIVERSITY MAKES NO WARRANTIES OF ANY KIND, EITHER EXPRESSED OR IMPLIED, AS TO ANY MATTER INCLUDING,
BUT NOT LIMITED TO, WARRANTY OF FITNESS FOR PURPOSE OR MERCHANTABILITY, EXCLUSIVITY,
OR RESULTS OBTAINED FROM USE OF THE MATERIAL. CARNEGIE MELLON UNIVERSITY DOES NOT MAKE ANY WARRANTY
OF ANY KIND WITH RESPECT TO FREEDOM FROM PATENT, TRADEMARK, OR COPYRIGHT INFRINGEMENT.
Released under a GNU GPL 3.0-style license, please see license.txt or contact permission@sei.cmu.edu for full terms.
[DISTRIBUTION STATEMENT A] This material has been approved for public release and unlimited distribution.
Please see Copyright notice for non-US Government use and distribution.
This Software includes and/or makes use of the following Third-Party Software subject to its own license:
1. Moodle (https://docs.moodle.org/dev/License) Copyright 1999 Martin Dougiamas.
DM20-0196
 */

/**
 * crucible Attempt wrapper class to encapsulate functions needed to individual
 * attempt records
 *
 * @package     mod_crucible
 * @copyright   2020 Carnegie Mellon Univeristy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class crucible_attempt {

    /** Constants for the status of the attempt */
    /** @var int Status constant representing a not-yet-started attempt. */
    const NOTSTARTED = 0;

    /** @var int Status constant representing an in-progress attempt. */
    const INPROGRESS = 10;

    /** @var int Status constant representing an abandoned attempt. */
    const ABANDONED = 20;

    /** @var int Status constant representing a finished attempt. */
    const FINISHED = 30;

    /** @var \stdClass The attempt record */
    protected $attempt;

    // TODO remove context if we dont use it.
    /** @var \context_module $context The context for this attempt */
    protected $context;

    /**
     * Construct the class.  if a dbattempt object is passed in set it,
     * otherwise initialize empty class
     *
     * @param questionmanager $questionmanager
     * @param \stdClass
     * @param \context_module $context
     */
    public function __construct($dbattempt = null, $context = null) {
        $this->context = $context;

        // If empty create new attempt.
        if (empty($dbattempt)) {
            $this->attempt = new \stdClass();

        } else { // Else load it up in this class instance.
            $this->attempt = $dbattempt;
        }
    }

    /**
     * Get the attempt stdClass object
     *
     * @return null|\stdClass
     */
    public function get_attempt() {
        return $this->attempt;
    }

    /**
     * returns a string representation of the status that is actually stored
     *
     * @return string
     * @throws \Exception throws exception upon an undefined status
     */
    public function getstate() {

        switch ($this->attempt->state) {
            case self::NOTSTARTED:
                return 'notstarted';
            case self::INPROGRESS:
                return 'inprogress';
            case self::ABANDONED:
                return 'abandoned';
            case self::FINISHED:
                return 'finished';
            default:
                throw new \Exception('undefined status for attempt');
                break;
        }
    }

    /**
     * Set the status of the attempt and then save it
     *
     * @param string $status
     *
     * @return bool
     */
    public function setstate($status) {

        switch ($status) {
            case 'notstarted':
                $this->attempt->state = self::NOTSTARTED;
                break;
            case 'inprogress':
                $this->attempt->state = self::INPROGRESS;
                break;
            case 'abandoned':
                $this->attempt->state = self::ABANDONED;
                break;
            case 'finished':
                $this->attempt->state = self::FINISHED;
                break;
            default:
                return false;
                break;
        }

        // Save the attempt.
        return $this->save();
    }

    /**
     * saves the current attempt class
     *
     * @return bool
     */
    public function save() {
        global $DB;
        // TODO check for undefined.
        if (is_null($this->attempt->endtime)) {
            debugging("null endtime passed to attempt->save for " . $this->attempt->id, DEBUG_DEVELOPER);
        }

        $this->attempt->timemodified = time();

        if (isset($this->attempt->id)) { // Update the record.

            try {
                $DB->update_record('crucible_attempts', $this->attempt);
            } catch (\Exception $e) {
                debugging($e->getMessage());

                return false; // Return false on failure.
            }
        } else {
            // Insert new record.
            try {
                $newid = $DB->insert_record('crucible_attempts', $this->attempt);
                $this->attempt->id = $newid;
            } catch (\Exception $e) {
                return false; // Return false on failure.
            }
        }

        return true; // Return true if we get here.
    }

    /**
     * Closes the attempt
     *
     * @param \mod_crucible\crucible
     *
     * @return bool Weather or not it was successful
     */
    public function close_attempt() {
        global $USER;

        $this->attempt->state = self::FINISHED;
        $this->attempt->timefinish = time();
        $this->save();

        $params = [
            'objectid'      => $this->attempt->crucibleid,
            'context'       => $this->context,
            'relateduserid' => $USER->id,
        ];

        // TODO verify this info is gtg and send the event
        // $event = \mod_crucible\event\attempt_ended::create($params);
        // $event->add_record_snapshot('crucible_attempts', $this->attempt);
        // $event->trigger();

        return true;
    }

    /**
     * Magic get method for getting attempt properties
     *
     * @param string $prop The property desired
     *
     * @return mixed
     * @throws \Exception Throws exception when no property is found
     */
    public function __get($prop) {

        if (property_exists($this->attempt, $prop)) {
            return $this->attempt->$prop;
        }

        // Otherwise throw a new exception.
        throw new \Exception('undefined property(' . $prop . ') on crucible attempt');

    }


    /**
     * magic setter method for this class
     *
     * @param string $prop
     * @param mixed  $value
     *
     * @return crucible_attempt
     */
    public function __set($prop, $value) {
        if (is_null($this->attempt)) {
            $this->attempt = new \stdClass();
        }
        $this->attempt->$prop = $value;

        return $this;
    }

}
