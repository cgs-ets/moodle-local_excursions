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
/**
 * Form definition for posting.
 *
 * @package   local_excursions
 * @copyright 2021 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_excursions\forms;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/repository/lib.php');

use \local_excursions\locallib;
use \local_excursions\libs\eventlib;

class form_event extends \moodleform {

    /**
     * Form definition
     *
     * @return void
     */
    function definition() {
        global $CFG, $OUTPUT, $USER, $DB;

        $mform =& $this->_form;
        $edit = $this->_customdata['edit'];
        $recurring = $this->_customdata['recurring'];

        /*----------------------
         *   General
         *----------------------*/
        $mform->addElement('header', 'general', '');
        $mform->setExpanded('general', true, true);

        $typehtml = '
            <h5>You are ' . ($edit ? 'editing' : 'creating') . ' an event</h5>
            <a href="/local/excursions/activity.php?create=1" style="color:blue;font-weight:600">Click here to create an excursion or incursion <i class="fa fa-external-link" aria-hidden="true"></i></a><br>
            <br>
        ';
        $mform->addElement('html', $typehtml);

        if ($recurring) {
            /*----------------------
            *   Series or event
            *----------------------*/
            $radioarray = array();
            $radioarray[] = $mform->createElement('radio', 'editseries', '', 'This occurance', 'event', '');
            $radioarray[] = $mform->createElement('radio', 'editseries', '', 'The entire series', 'series', '');
            $mform->addElement('html', '<br><strong>This event is part of a series. What do you want to edit?</strong>');
            $mform->addGroup($radioarray, 'editseries', '', array(' '), false);
            $mform->setDefault('editseries', 'event');
            $mform->addElement('html', '<div class="alert alert-warning">Editing a single occurance will detach it from the series. Editing an entire series will recreate all events in the series based on the information submitted below.</div><br>');
        } 

        /*----------------------
        *   Name
        *----------------------*/
        $mform->addElement('text', 'activityname', "Event title", 'size="48"');
        $mform->setType('activityname', PARAM_TEXT);
        $mform->addRule('activityname', get_string('required'), 'required', null, 'client');


        /*----------------------
        *   On or Off campus
        *----------------------*/
        $radioarray = array();
        $radioarray[] = $mform->createElement('radio', 'campus', '', 'On campus', 'oncampus', '');
        $radioarray[] = $mform->createElement('radio', 'campus', '', 'Off campus', 'offcampus', '');
        $mform->addGroup($radioarray, 'campus', get_string('activityform:campus', 'local_excursions'), array(' '), false);
        $mform->setDefault('campus', 'oncampus');

        
        /*----------------------
        *   Location
        *----------------------*/
        $mform->addElement('text', 'location', "Location", 'size="48"');
        $mform->setType('location', PARAM_TEXT);


        /*----------------------
        *   Start and end times
        *----------------------*/
        $mform->addElement('date_time_selector', 'timestart', get_string('activityform:timestart', 'local_excursions'));
        $mform->addElement('date_time_selector', 'timeend', get_string('activityform:timeend', 'local_excursions'));
        $dt = new \DateTime('@' . time());
        $dt->setTime( $dt->format("H"), 0, 0);
        $mform->setDefault('timestart', $dt->getTimestamp());
        $mform->setDefault('timeend', $dt->getTimestamp());

        /* Is recurring */
        $mform->addElement('advcheckbox', 'recurring', 'Recurring', '', [], [0,1]);
        if ($recurring) {
            $mform->hideIf('recurring', 'editseries', 'eq', 'event');
        }

        /* Recurring pattern */
        $radioarray=array();
        $radioarray[] = $mform->createElement('radio', 'recurringpattern', '', "Daily", "daily");
        $radioarray[] = $mform->createElement('radio', 'recurringpattern', '', "Weekly", "weekly");
        $mform->addGroup($radioarray, 'recurringpatternar', '', array(' '), false);
        $mform->setDefault('recurringpattern', 'weekly');
        $mform->hideIf('recurringpatternar', 'recurring', 'neq', 1);
        if ($recurring) {
            $mform->hideIf('recurringpatternar', 'editseries', 'eq', 'event');
        }

        /* Recurring DAILY pattern */
        $radioarray=array();
        $radioarray[] = $mform->createElement('radio', 'recurringdailypattern', '', "All days", "alldays");
        $radioarray[] = $mform->createElement('radio', 'recurringdailypattern', '', "Week days", "weekdays");
        $mform->addGroup($radioarray, 'recurringdailypatternar', '', array(' '), false);
        $mform->setDefault('recurringdailypattern', 'weekdays');
        $mform->hideIf('recurringdailypatternar', 'recurringpattern', 'neq', 'daily');
        $mform->hideIf('recurringdailypatternar', 'recurring', 'neq', 1);
        if ($recurring) {
            $mform->hideIf('recurringdailypatternar', 'editseries', 'eq', 'event');
        }

        /* Recur until */
        $mform->addElement('date_time_selector', 'recuruntil', 'Until');
        $mform->hideIf('recuruntil', 'recurring', 'neq', 1);
        if ($recurring) {
            $mform->hideIf('recuruntil', 'editseries', 'eq', 'event');
        }

        /* Calculated dates */
        $mform->addElement('html', '<div id="calculated-dates"></div>');

        /*----------
        * Non negotiable
        * ----------------*/
        $mform->addElement('html', '<br>');
        $mform->addElement('advcheckbox', 'nonnegotiable', 'The dates entered for this event are non negotiable', '', [], [0,1]);
        $mform->addElement('textarea', 'nonnegotiablereason', "Why is this event time non-negotiable?", 'wrap="virtual" rows="2" cols="30"');
        $mform->setType('notes', PARAM_TEXT);
        $mform->hideIf('nonnegotiablereason', 'nonnegotiable', 'neq', 1);


        // Check conflicts button
        //$mform->addElement('html', '<button style="margin-right: 10px;" class="btn btn-primary btn-checkconflicts">Check for conflicts</button>');
        //$mform->addElement('html', '<span id="conflicts"></span><br><br>');


        /*-----------------------
        *  Categories
        *-----------------------*/
        $html = '
        <div class="form-group row fitem"><div class="col-md-3">Categories<br><small>For display purposes</small><br></div><div class="col-md-9">
        <div class="categoriescontainer"></div>
        </div></div>';
        $mform->addElement('html', $html);
        $mform->addElement('text', 'categoriesjson', 'CategoriesJSON');
        $mform->setType('categoriesjson', PARAM_RAW);

        /*-----------------------
        * Affected areas
        *-----------------------*/
        $areashtml = '
        <div class="form-group row fitem"><div class="col-md-3">Affected areas<br><small>For planning purposes</small><br></div><div class="col-md-9">
        <div class="areascontainer"></div>
        </div></div>';
        $mform->addElement('html', $areashtml);
        $mform->addElement('text', 'areasjson', 'Owner JSON');
        $mform->setType('areasjson', PARAM_RAW);

        /*----------------------
        *   Notes
        *----------------------*/
        $mform->addElement('textarea', 'notes', "Details", 'wrap="virtual" rows="7" cols="30"');
        $mform->setType('notes', PARAM_TEXT);

        
        /*----------------------
        *   Owner
        *----------------------*/
        $staffselectorhtml = $OUTPUT->render_from_template('local_excursions/staff_selector', array(
            'id' => 'owner', 
            'maxusers' => 1,
            'question' => "Owner",
        )); 
        $mform->addElement('html', $staffselectorhtml);
        $mform->addElement('text', 'ownerjson', 'Owner JSON');
        $mform->setType('ownerjson', PARAM_RAW);


        //$mform->addElement('html', '<div class="alert alert-danger force-check-conflicts"><p>You must check for conflicts before you can submit.</p><button class="btn btn-danger btn-checkconflicts">Check for conflicts</button></div>');
        $this->add_action_buttons(true, 'Submit');


        // Hidden fields.
        $mform->addElement('hidden', 'edit');
        $mform->setType('edit', PARAM_INT);
        $mform->addElement('hidden', 'action');
        $mform->setType('action', PARAM_RAW);

    }


    // Perform some extra moodle validation.
    function validation($data, $files) {
        $errors = array();

        if ($data['action'] == 'cancel' || $data['action'] == 'delete') {
            return [];
        }

        if ($data['timeend'] <= $data['timestart']) {
            $errors['timeend'] = 'End time must be greater than start time';
        }

        return $errors;
    }

}