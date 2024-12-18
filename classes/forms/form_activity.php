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
use \local_excursions\persistents\activity;
use \local_excursions\external\activity_exporter;

class form_activity extends \moodleform {

    /**
     * Returns the options array to use in filemanager for announcement attachments
     *
     * @return array
     */
    public static function ra_options() {
        global $CFG;

        return array(
            'subdirs' => 0,
            'maxbytes' => 0,
            'maxfiles' => 1,
            'accepted_types' => '*',
            'return_types' => FILE_INTERNAL | FILE_CONTROLLED_LINK
        );
    }

    /**
     * Returns the options array to use in filemanager for announcement attachments
     *
     * @return array
     */
    public static function attachment_options() {
        global $CFG;

        return array(
            'subdirs' => 0,
            'maxbytes' => 0,
            'maxfiles' => 10,
            'accepted_types' => '*',
            'return_types' => FILE_INTERNAL | FILE_CONTROLLED_LINK
        );
    }

    /**
     * Form definition
     *
     * @return void
     */
    function definition() {
        global $CFG, $OUTPUT, $USER, $DB;

        $mform =& $this->_form;
        $edit = $this->_customdata['edit'];

        if ($edit) {
            $activity = new activity($edit);
            $activityexporter = new activity_exporter($activity);
            $activity = $activityexporter->export($OUTPUT);
        } else {
            // Should never get here.
            exit;
        }

        /****
        * Can't use client validation when using custom action buttons. Validation is done on server in activity.php.
        ****/

        /*---------
        * Status. Only visible when acitivity is approved.
        ---------*/
        $statushtml = $OUTPUT->render_from_template('local_excursions/activityform_approvals_status', array('statushelper' => $activity->statushelper));
        $mform->addElement('html', $statushtml);
                

        /*----------------------
         *   General
         *----------------------*/
        $mform->addElement('header', 'general', '');
        $mform->setExpanded('general', true, true);

            /*----------------------
             *   Name
             *----------------------*/
            $mform->addElement('text', 'activityname', get_string('activityform:name', 'local_excursions'), 'size="48"');
            $mform->setType('activityname', PARAM_TEXT);

            /*----------------------
             *   Acitivty type - Incursion / Excursion
             *----------------------*/
            $radioarray = array();
            $radioarray[] = $mform->createElement('radio', 'activitytype', '', 'Excursion', 'excursion', '');
            $radioarray[] = $mform->createElement('radio', 'activitytype', '', 'Incursion', 'incursion', '');
            $mform->addGroup($radioarray, 'activitytype', get_string('activityform:activitytype', 'local_excursions'), array(' '), false);
            $mform->setDefault('activitytype', 'excursion');

            /*----------------------
             *   Campus
             *----------------------*/
            $radioarray = array();
            $radioarray[] = $mform->createElement('radio', 'campus', '', 'Primary School', 'primary', '');
            $radioarray[] = $mform->createElement('radio', 'campus', '', 'Senior School', 'senior', '');
            $radioarray[] = $mform->createElement('radio', 'campus', '', 'Whole School', 'whole', '');
            $radioarray[] = $mform->createElement('radio', 'campus', '', 'Commercial Events', 'campusmng', '');
            $mform->addGroup($radioarray, 'campus', "Review workflow", array(' '), false);

            /*----------------------
             *   Location
             *----------------------*/
            $mform->addElement('text', 'location', get_string('activityform:location', 'local_excursions'), 'size="48"');
            $mform->setType('location', PARAM_TEXT);
            $mform->addElement('html', '<div class="form-group row fitem"><div class="col-md-3"></div><div class="campus-desc col-md-9">You are responsible for booking arrangements. For internal bookings use SOBS.</div></div>');


            /*----------------------
             *   Start and end times
             *----------------------*/
            $mform->addElement('date_time_selector', 'timestart', get_string('activityform:timestart', 'local_excursions'));
            $mform->addElement('date_time_selector', 'timeend', get_string('activityform:timeend', 'local_excursions'));


            /*----------------------
             *   Notes
             *----------------------*/
            $mform->addElement('textarea', 'notes', get_string("activityform:notes", "local_excursions"), 'wrap="virtual" rows="7" cols="51"');
            $mform->setType('notes', PARAM_TEXT);

            /*----------------------
             *   Transport
             *----------------------*/
            $mform->addElement('textarea', 'transport', get_string("activityform:transport", "local_excursions"), 'wrap="virtual" rows="3" cols="51"');
            $mform->setType('transport', PARAM_TEXT);

            /*----------------------
             *   Cost
             *----------------------*/
            $mform->addElement('text', 'cost', get_string('activityform:cost', 'local_excursions'));
            $mform->setType('cost', PARAM_TEXT);

        /*----------------------
         *   Leadership
         *----------------------*/

            $mform->addElement('header', 'staff', get_string("activityform:staff", "local_excursions"));
            $mform->setExpanded('staff', true, true);

            /*----------------------
             *   Staff in charge
             *----------------------*/
            $staffselectorhtml = $OUTPUT->render_from_template('local_excursions/staff_selector', array(
                'id' => 'staffincharge', 
                'maxusers' => 1,
                'question' => get_string("activityform:staffincharge", "local_excursions"),
            )); 
            $mform->addElement('html', $staffselectorhtml);
            $mform->addElement('text', 'staffinchargejson', 'Staff in Charge');
            $mform->setType('staffinchargejson', PARAM_RAW);

            /*----------------------------
             *   Additional planning staff
             *---------------------------*/
            $staffselectorhtml = $OUTPUT->render_from_template('local_excursions/staff_selector', array(
                'id' => 'planningstaff', 
                'maxusers' => 0,
                'question' => get_string("activityform:planningstaff", "local_excursions"),
            )); 
            $mform->addElement('html', $staffselectorhtml);
            $mform->addElement('text', 'planningstaffjson', 'Planning Staff');
            $mform->setType('planningstaffjson', PARAM_RAW);


            /*----------------------
             *   Accompanying staff
             *----------------------*/
            $staffselectorhtml = $OUTPUT->render_from_template('local_excursions/staff_selector', array(
                'id' => 'accompanyingstaff', 
                'maxusers' => 0,
                'question' => get_string("activityform:accompanyingstaff", "local_excursions"),
            )); 
            $mform->addElement('html', $staffselectorhtml);
            $mform->addElement('text', 'accompanyingstaffjson', 'Accompanying Staff');
            $mform->setType('accompanyingstaffjson', PARAM_RAW);

            /*----------------------
             *   Non-school Participants
             *----------------------*/
            $mform->addElement('textarea', 'otherparticipants', get_string("activityform:otherparticipants", "local_excursions"), 'wrap="virtual" rows="4" cols="51"');
            $mform->setType('otherparticipants', PARAM_TEXT);

        /*----------------------
         *   Students
         *----------------------*/
        $mform->addElement('header', 'students', get_string("activityform:students", "local_excursions"));
        $mform->setExpanded('students', true, true);

        $mform->addElement('text', 'studentlistjson', 'Students list');
        $mform->setType('studentlistjson', PARAM_RAW);

        // Hidden inputs to track the limit and dueby values.
        $mform->addElement('hidden', 'hiddeninvitetype');
        $mform->setType('hiddeninvitetype', PARAM_RAW);
        $mform->addElement('hidden', 'hiddenlimit');
        $mform->setType('hiddenlimit', PARAM_RAW);
        $mform->addElement('hidden', 'hiddendueby');
        $mform->setType('hiddendueby', PARAM_RAW);

        // Inject textarea and placeholders in the prepare message section.
        $activity->emailsubject = 'Subject: Permissions required for: ' . $activity->activityname;
        $activity->extratext = '<textarea id="permissions-extra-text" placeholder="Optionally enter custom text here..."></textarea>';
        $activity->parentname = '{parent name will be added automatically}';
        $activity->studentname = '{student name will be added automatically}';
        $activity->buttonoverride = '{A button prompting the parent to respond appears here}';

        // Create a dueby date picker.
        $dueby = $mform->createElement('date_time_selector', 'timedueby');
        $timedueby = $activity->permissionsdueby;
        if (!$timedueby) {
            $timedueby = $activity->timestart;
        }
        $value = array(
            'minute' => date('i', $timedueby),
            'hour' => date('G', $timedueby),
            'day' => date('j', $timedueby),
            'month' => date('n', $timedueby),
            'year' => date('Y', $timedueby),
        );
        $dueby->setValue($value);
        $activity->duebydatefield = $dueby->toHtml();

        // Finally add the rendered html for the student list and permissions area to the form.
        // Change the classname to disable moodle's own js for this.
        $studentshtml = $OUTPUT->render_from_template('local_excursions/activityform_studentlist', $activity);
        $mform->addElement('html', str_replace('fdate_time_selector', 'xfdate_time_selector', $studentshtml));

        /*----------------------
         *   Paperwork
         *----------------------*/
        $mform->addElement('header', 'paperwork', get_string("activityform:paperwork", "local_excursions"));
        $mform->setExpanded('paperwork', true, true);

            /*----------------------
             *   Medical report
             *----------------------*/
            $medihtml = $OUTPUT->render_from_template('local_excursions/activityform_medicalreport', array('activityid' => $edit));
            $mform->addElement('html', $medihtml);

            /*----------------------
             *   RA
             *----------------------*/
            if ($activity->usercanedit) { //$activity->isstaffincharge || $activity->iscreator || $activity->isapprover || $activity->isplanner) {
                $mform->addElement('filemanager', 'riskassessment', get_string("activityform:riskassessment", "local_excursions"), null, self::ra_options());
            } else {
                // list the files.
                $rahtml = $OUTPUT->render_from_template('local_excursions/activityform_download_ra', array('files' => $activity->formattedra));
                $mform->addElement('html', $rahtml);
            }

            /*----------------------
             *   Additional attachments
             *----------------------*/
            if ($activity->usercanedit) { //activity->isstaffincharge || $activity->iscreator || $activity->isapprover) {
                $mform->addElement('filemanager', 'attachments', get_string('activityform:attachments', 'local_excursions'), null,
                    self::attachment_options());
            } else {
                // list the files.
                $attachmentshtml = $OUTPUT->render_from_template('local_excursions/activityform_download_atts', array('files' => $activity->formattedattachments));
                $mform->addElement('html', $attachmentshtml);
            }

            /*----------------------
             *   Chargesheet
             *----------------------*/
            if ($activity->statushelper->isapproved && $activity->cost) {
                $chargesheethtml = $OUTPUT->render_from_template('local_excursions/activityform_chargesheet', array('activityid' => $edit));
                $mform->addElement('html', $chargesheethtml);
            }
            
            /*----------------------
             *   Resources
             *----------------------*/
            //if ($activity->statushelper->isapproved) {
                $resourceshtml = $OUTPUT->render_from_template('local_excursions/activityform_resources', array());
                $mform->addElement('html', $resourceshtml);
            //}

        // Buttons.
        if ($activity->usercanedit) {
            $mform->addElement('header', 'actions', '');
            $mform->setExpanded('actions', true, true);
            //$mform->addElement('html', $OUTPUT->render_from_template('local_excursions/activityform_buttons', array(
            //  'activity' => $activity,
            //)));
            $buttonarray = array();
            if ($activity->statushelper->cansavedraft) {
                $buttonarray[] = &$mform->createElement('submit','savedraft', get_string('activityform:savedraft', 'local_excursions'));
                $buttonarray[] = &$mform->createElement('submit','sendforreview', get_string('activityform:sendforreview', 'local_excursions'));
            } else {
                $mform->addElement('html', '<div class="alert alert-danger" role="alert">' . get_string('activityform:savemayinvalidate', 'local_excursions') . '</div>');
                $buttonarray[] = &$mform->createElement('submit','sendforreview', get_string('activityform:savechanges', 'local_excursions'));
            }   
            $buttonarray[] = &$mform->createElement('submit','cancel', get_string('cancel', 'moodle'));
            if (($activity->isapprover) || ($activity->iscreator && !$activity->statushelper->isapproved)) {
                $buttonarray[] = &$mform->createElement('submit', 'delete', get_string('activityform:delete', 'local_excursions'));
            }

            $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        }

        $mform->addElement('html', $OUTPUT->render_from_template('local_excursions/activityform_usefullinks', array('activity' => $activity)));

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

        if (!is_numeric($data['cost'])) {
            $errors['cost'] = 'Cost must be a numeric value (integer or decimal). Do not enter symbols such as "$" or currencies.';
        }

        if ($data['action'] != 'sendforreview') {
            return $errors;
        }

        // Validation for review stage only.

        return $errors;
    }

    function get_element_label($name) {
        $mform =& $this->_form;

        $label = '';

        if ($mform->elementExists($name)) {
            // Get detils from form element.
            $element = $mform->getElement($name);
            $name = $element->getName();
            $label = $element->getLabel();

            // Sanitise the label.
            $label = strpos($label, "<br>") ? substr($label, 0, strpos($label, "<br>")) : $label; // Remove the small descriptions.

            // Give the hidden fields a name.
            if ($name == 'permissionstype') {
                $label = 'Permissions invite type'; 
            }
            if ($name == 'permissionslimit') {
                $label = 'Permissions limit'; 
            }
            if ($name == 'permissionsdueby') {
                $label = 'Permissions due by'; 
            }
        }
        
        return $label;
    }
}