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
use \local_excursions\persistents\activity;

$edit = optional_param('edit', 0, PARAM_INT);

require_login();
locallib::require_cgs_staff();

$editurl = new moodle_url('/local/excursions/event.php', array(
    'edit' => $edit,
));
$viewurl = new moodle_url('/local/excursions/index.php');

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url($editurl);
$PAGE->set_title('Event settings');
$PAGE->set_heading('Event settings');

$event = null;
//$recurring = 0;
if ($edit) {
    $event = eventlib::get_event($edit);
    if (!$event) {
        redirect($viewurl->out(false));
        exit;
    }
    //$recurring = $event->recurrencemaster ? 1 : 0;
    // Is this user allowed to edit this event?
    $canedit = false;
    if (locallib::is_event_reviewer()) {
        $canedit = true;
    } else if ($event->owner == $USER->username || $event->creator == $USER->username) {
        $canedit = true;
    }
    if (!$canedit) {
        $notice = 'You do not have permission to edit this calendar event.';
        redirect(
            $viewurl->out(),
            $notice,
            null,
            \core\output\notification::NOTIFY_ERROR
        );
        exit;
    }
}

// Instantiate the form.
$formactivity = new form_event(
    $editurl->out(false), 
    array(
        'edit' => $edit, 
        //'recurring' => $recurring, 
        'event' => $event
    ), 
    'post', 
    '', 
    array('data-form' => 'excursions-event', 'data-eventid' => $edit)
);

// Redirect to index if cancel was clicked.
if ($formactivity->is_cancelled()) {
    redirect($viewurl->out());
}

$formdata = $formactivity->get_data();

/******************
* Form submitted
*******************/
if (!empty($formdata)) {

    //echo "<pre>"; var_export($formdata); exit;

    $eventid = eventlib::save_event($formdata);
    $event = $DB->get_record('excursions_events', array('id' => $eventid));

    // Creating a new excursion.
    if ($formdata->entrytype == 'excursion') {
        // Redirect to edit.
        $activitymanageurl = new moodle_url('/local/excursions/activity.php', array(
            'edit' => $event->activityid,
        ));
        $notice = get_string("activityform:savechangessuccess", "local_excursions");
        redirect($activitymanageurl->out(false), $notice, null, \core\output\notification::NOTIFY_SUCCESS);
        exit;
    }

    $notice = get_string("activityform:savechangessuccess", "local_excursions");
    redirect(
        $viewurl->out(),
        $notice,
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
    exit;

} 
else 
{
    /*********************
    * New event or Edit event
    **********************/
    $data = new \stdClass();
    $data->general = '';
    $data->edit = $edit;
    $owneruser = (object) locallib::get_recipient_user($USER);
    $data->ownerjson = json_encode([$owneruser]);
    if ($edit) {
        $data->activityname = $event->activityname;
        $data->activitytype = $event->activitytype;
        $data->location = $event->location;
        $data->timestart = $event->timestart;
        $data->timeend = $event->timeend;
        $data->nonnegotiable = $event->nonnegotiable;
        $data->nonnegotiablereason = $event->reason;
        $data->notes = $event->notes;
        $data->categoriesjson = $event->categoriesjson;
        $data->areasjson = $event->areasjson;
        $data->ownerjson = $event->ownerjson;
        //$data->recurring = $recurring;
        $data->displaypublic = $event->displaypublic;
        $data->entrytype = $event->isactivity ? 'excursion' : 'event';
        /*if ($recurring) {
            list($data->recurring, $data->recurringpattern, $data->recurringdailypattern, $data->recuruntil) = array_values(json_decode($event->recurringjson, true));
        }*/
    }

    // Set the form values.
    $formactivity->set_data($data);
}

// Load the form.
// Add css.
$PAGE->requires->css(new moodle_url($CFG->wwwroot . '/local/excursions/excursions.css', array('nocache' => rand())));
// Add scripts.
$PAGE->requires->js_call_amd('local_excursions/eventform', 'init');
// https://github.com/daweilv/treejs
$PAGE->requires->js( new moodle_url($CFG->wwwroot . '/local/excursions/js/tree.min.js'), true );
$PAGE->requires->js( new moodle_url($CFG->wwwroot . '/local/excursions/js/calcategories.js', array('nocache' => rand())), true );
$PAGE->requires->js( new moodle_url($CFG->wwwroot . '/local/excursions/js/eventareas.js', array('nocache' => rand())), true );



// Body classes.
$PAGE->add_body_class('limitedwidth');
$output = $OUTPUT->header();

$output .= $formactivity->render();

$output .= $OUTPUT->footer();

echo $output;

exit;