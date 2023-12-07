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
 * A student portfolio tool for CGS.
 *
 * @package   local_excursions
 * @copyright 2021 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');
require_once($CFG->libdir.'/filelib.php');

use \local_excursions\forms\form_activity;
use local_excursions\external\activity_exporter;
use \local_excursions\persistents\activity;
use \local_excursions\locallib;

$edit = optional_param('edit', 0, PARAM_INT);
$create = optional_param('create', 0, PARAM_INT);

require_login();
locallib::require_cgs_staff();

$activitymanageurl = new moodle_url('/local/excursions/activity.php', array(
    'edit' => $edit,
));
$viewurl = new moodle_url('/local/excursions/index.php');

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url($activitymanageurl);
$PAGE->set_title(get_string('activitiesetup', 'local_excursions'));
$PAGE->set_heading(get_string('activitiesetup', 'local_excursions'));
$PAGE->navbar->add(get_string('activities', 'local_excursions'), new moodle_url('/local/excursions/index.php'));

// Create a new empty activity.
$activity = null;

// Determine if creating new / editing / or viewing list.
if ($create) {
    // Create a new empty activity.
    $data = new \stdClass();
    $data->username = $USER->username;
    $data->staffincharge = $USER->username;
    $data->permissionstype = 'system';
    $data->status = locallib::ACTIVITY_STATUS_AUTOSAVE;
    $data->cost = '0';
    $activity = new activity(0, $data);
    $activity->save();

    // Redirect to edit.
    $activitymanageurl->param('edit', $activity->get('id'));
    redirect($activitymanageurl->out(false));
    exit;

} elseif ($edit) {
    // Load existing activity.
    $exists = activity::record_exists($edit);
    if ($exists) {
        $activity = new activity($edit);
    }

    if (!$exists || $activity->get('deleted')) {
        redirect($viewurl->out(false));
        exit;
    }

} else {
    // This should never happen.
    $notice = "Could not open Activity form. A required parameter was missing.";
    redirect(
        $viewurl->out(),
        $notice,
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

// Instantiate the form.
$formactivity = new form_activity(
    $activitymanageurl->out(false), 
    array(
        'edit' => $edit,
    ), 
    'post', 
    '', 
    array('data-form' => 'excursions-activity')
);

// Set up the draft file areas.
$draftra = file_get_submitted_draft_itemid('ra');
$raoptions = form_activity::ra_options();
file_prepare_draft_area($draftra, $context->id, 'local_excursions', 
    'ra', $edit, $raoptions);

$draftatt = file_get_submitted_draft_itemid('attachments');
$attoptions = form_activity::attachment_options();
file_prepare_draft_area($draftatt, $context->id, 'local_excursions', 
    'attachments', $edit, $attoptions);

// Get existing data.
// Export the activity for additional stuff.
$activityexporter = new activity_exporter($activity);
$activity = $activityexporter->export($OUTPUT);
$data = new \stdClass();
$data->general = '';
$data->edit = $edit;
$data->activityname = $activity->activityname;
$data->campus = $activity->campus;
if (empty($data->campus)) {
    $data->campus = 'primary';
    if (strpos($USER->profile['CampusRoles'], 'Senior School:Staff') !== false) {
        $data->campus = 'senior';
    }
}
$data->activitytype = $activity->activitytype;
if (empty($data->activitytype)) {
    $data->activitytype = 'excursion';
}
$data->transport = $activity->transport;
$data->cost = $activity->cost;
$data->location = $activity->location;
$data->timestart = $activity->timestart;
$data->timeend = $activity->timeend;
$data->notes = $activity->notes;
$data->studentlistjson = $activity->studentlistjson;
$data->staffinchargejson = $activity->staffinchargejson;
$data->hiddeninvitetype = $activity->permissionstype;
$data->hiddenlimit = $activity->permissionslimit;
$data->hiddendueby = $activity->permissionsdueby;
$data->riskassessment = $draftra;
$data->attachments = $draftatt;
$data->planningstaffjson = $activity->planningstaffjson;
$data->accompanyingstaffjson = $activity->accompanyingstaffjson;
$data->otherparticipants = $activity->otherparticipants;

// User is default staff in charge.
if (empty($data->staffinchargejson)) {
    $staffinchargeuser = (object) locallib::get_recipient_user($USER);
    $data->staffinchargejson = json_encode([$staffinchargeuser]);
}

// Set the form values.
$formactivity->set_data($data);

// Run get_data to check for submission, trigger validation and set errors.
$formdata = $formactivity->get_data();

// Form submitted - process data and redirect to index.
if (!empty($formdata)) {

    $activity = new activity($edit);

    // Cancel.
    if ($formdata->action == 'cancel') {
        // If not yet saved yet, delete the activity as it is just an autosave.
        if ($activity->get('status') == locallib::ACTIVITY_STATUS_AUTOSAVE) {
            $activity->delete();
        }
        redirect($viewurl->out());
        exit;
    }

    // Delete.
    if ($formdata->action == 'delete') {
        // If not yet saved yet, delete the activity as it is just an autosave.
        if ($activity->get('status') == locallib::ACTIVITY_STATUS_AUTOSAVE) {
            $activity->delete();
        } else {
            activity::soft_delete($edit);
        }
        redirect($viewurl->out());
        exit;
    }

    $data = new \stdClass();
    $data->id = $edit;
    // If currently just an autosave, set status to intentional draft.
    $data->status = $activity->get('status');
    $statushelper = locallib::status_helper($data->status);
    if ($statushelper->isautosave) {
        $data->status = locallib::ACTIVITY_STATUS_DRAFT;
    }
    $data->activityname = $formdata->activityname;
    $data->campus = $formdata->campus;
    $data->activitytype = $formdata->activitytype;
    $data->location = $formdata->location;
    $data->timestart = $formdata->timestart;
    $data->timeend = $formdata->timeend;
    $data->notes = $formdata->notes;
    $data->staffinchargejson = $formdata->staffinchargejson;
    $data->studentlistjson = $formdata->studentlistjson;
    $data->transport = $formdata->transport;
    $data->cost = $formdata->cost;
    $data->planningstaffjson = $formdata->planningstaffjson;
    $data->accompanyingstaffjson = $formdata->accompanyingstaffjson;
    $data->otherparticipants = $formdata->otherparticipants;
    $data->permissionstype = $formdata->hiddeninvitetype;
    $data->permissionslimit = $formdata->hiddenlimit;
    $data->permissionsdueby = $formdata->hiddendueby;
    if (!is_numeric($formdata->hiddendueby)) {
        $dueby = json_decode($formdata->hiddendueby);
        $duebystring = "{$dueby[2]}-{$dueby[1]}-{$dueby[0]} {$dueby[3]}:{$dueby[4]}"; // Format yyyy-m-d h:m.
        $data->permissionsdueby = strtotime($duebystring);
    }
    if (isset($formdata->riskassessment)) {
        $data->riskassessment = $formdata->riskassessment;
    }
    if (isset($formdata->attachments)) {
        $data->attachments = $formdata->attachments;
    }

    $notice = get_string("activityform:savechangessuccess", "local_excursions");

    if ($formdata->action == 'sendforreview') {
        // if the activity is currently a draft, bump it to in-review.
        if ($statushelper->isautosave || $statushelper->isdraft) {
            $data->status = locallib::ACTIVITY_STATUS_INREVIEW;
            $notice = get_string("activityform:sentforreviewsuccess", "local_excursions");
        }
    }

    $result = activity::save_from_data($data);

    if ($result) {
        redirect(
            $viewurl->out(),
            $notice,
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
        exit;
    } 
    $notice = get_string("activityform:savefail", "local_excursions");
    redirect(
        $viewurl->out(),
        $notice,
        null,
        \core\output\notification::NOTIFY_ERROR
    );
    exit;
}

// Load the form.
// Add css.
$PAGE->requires->css(new moodle_url($CFG->wwwroot . '/local/excursions/excursions.css', array('nocache' => rand())));
// Add scripts.
$PAGE->requires->js_call_amd('local_excursions/activityform', 'init');

// Body classes.
$PAGE->add_body_class('preloading');
$PAGE->add_body_class('activity-status-' . $activity->status);
if ($activity->usercanedit) {
    $PAGE->add_body_class('activity-canedit');
}
if ($activity->isstaffincharge) {
    $PAGE->add_body_class('activity-staffincharge');
}
if ($activity->isplanner) {
    $PAGE->add_body_class('activity-planner');
}
if ($activity->isapprover) {
    $PAGE->add_body_class('activity-approver');
}
if ($activity->username == $USER->username) {
    $PAGE->add_body_class('activity-creator');
}
if ($activity->isaccompanying) {
    $PAGE->add_body_class('activity-accompanying');
}

$output = $OUTPUT->header();

$output .= $formactivity->render();

$output .= $OUTPUT->render_from_template('local_excursions/activityform_approvals', $activity);

$output .= $OUTPUT->footer();

echo $output;

exit;