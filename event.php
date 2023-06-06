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
 * Generic events
 *
 * @package   local_excursions
 * @copyright 2021 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');
require_once($CFG->libdir.'/filelib.php');

use \local_excursions\forms\form_event;
use \local_excursions\libs\eventlib;
use \local_excursions\locallib;

$edit = optional_param('edit', 0, PARAM_INT);

require_login();
locallib::require_cgs_staff();

$editurl = new moodle_url('/local/excursions/event.php', array(
    'edit' => $edit,
));
$viewurl = new moodle_url('/local/excursions/events.php');

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url($editurl);
$PAGE->set_title(get_string('activitiesetup', 'local_excursions'));
$PAGE->set_heading(get_string('activitiesetup', 'local_excursions'));
$PAGE->navbar->add(get_string('activities', 'local_excursions'), $viewurl);

// Create a new empty activity.
$activity = null;

// Instantiate the form.
$formactivity = new form_event(
    $editurl->out(false), 
    array('edit' => $edit), 
    'post', 
    '', 
    array('data-form' => 'excursions-event')
);

$formdata = $formactivity->get_data();

/******************
* Form submitted
*******************/
if (!empty($formdata)) {
    //var_export($formdata); 
    //exit;
    $event = eventlib::get_event($formdata->edit);
    if ($formdata->edit && $event === false) {
        // Editing but no event found. Major error.
        exit;
    }
    $event->activityname = $formdata->activityname;
    $event->campus = $formdata->campus;
    $event->location = $formdata->location;
    $event->timestart = $formdata->timestart;
    $event->timeend = $formdata->timeend;
    $event->nonnegotiable = $formdata->nonnegotiable;
    $event->notes = $formdata->notes;
    $event->categories = $formdata->categories;
    $event->areasjson = $formdata->areasjson;
    $event->ownerjson = $formdata->ownerjson;
    $event->owner = $USER->username;
    $owner = json_decode($formdata->ownerjson);
    if ($owner) {
        $owner = array_pop($owner);
        $event->owner = $owner->idfield;
    }
    eventlib::save_event($event);
    $notice = get_string("activityform:savechangessuccess", "local_excursions");
    redirect(
        $viewurl->out(),
        '<p>'.$notice.'</p>',
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
    exit;

} 
else 
{
    /******************
    * New event or Edit event
    *******************/
    $data = new \stdClass();
    $data->general = '';
    $data->edit = $edit;
    $owneruser = (object) locallib::get_recipient_user($USER);
    $data->ownerjson = json_encode([$owneruser]);
    $data->owner = $USER->username;
    if ($edit) {
        // Load existing activity.
        $event = eventlib::get_event($edit);
        if (!$event) {
            redirect($viewurl->out(false));
            exit;
        }

        // Get existing data.
        // Export the activity for additional stuff.
        $event = eventlib::export($event);
        $data->activityname = $event->activityname;
        $data->campus = $event->campus;
        if (empty($data->campus)) {
            $data->campus = 'primary';
            if (strpos($USER->profile['CampusRoles'], 'Senior School:Staff') !== false) {
                $data->campus = 'senior';
            }
        }
        $data->activitytype = $event->activitytype;
        if (empty($data->activitytype)) {
            $data->activitytype = 'oncampus';
        }
        $data->timestart = $event->timestart;
        $data->timeend = $event->timeend;
        $data->notes = $event->notes;
        $data->ownerjson = $event->ownerjson;

    }

    // Set the form values.
    $formactivity->set_data($data);
}

// Load the form.
// Add css.
$PAGE->requires->css(new moodle_url($CFG->wwwroot . '/local/excursions/excursions.css', array('nocache' => rand())));
// Add scripts.
$PAGE->requires->js_call_amd('local_excursions/eventform', 'init');
$PAGE->requires->js( new moodle_url($CFG->wwwroot . '/local/excursions/js/tree.min.js'), true );
$PAGE->requires->js( new moodle_url($CFG->wwwroot . '/local/excursions/js/calcategories.js'), true );
$PAGE->requires->js( new moodle_url($CFG->wwwroot . '/local/excursions/js/eventareas.js'), true );

// Body classes.
$PAGE->add_body_class('limitedwidth');
$output = $OUTPUT->header();

$output .= $formactivity->render();

$output .= $OUTPUT->footer();

echo $output;

exit;