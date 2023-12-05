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

/**
 * Prints an instance of local_excursions.
 *
 * @package     local_excursions
 * @copyright   2021 Michael Vangelovski
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');

use local_excursions\persistents\activity;
use \local_excursions\libs\eventlib;
use local_excursions\locallib;
use \local_excursions\external\activity_exporter;

require_login();
$isstaff = locallib::is_cgs_staff();

$nav = optional_param('nav', '', PARAM_RAW);
$status = optional_param('status', '', PARAM_RAW);
$campus = optional_param('campus', 'ws', PARAM_RAW);
$user = optional_param('user', '', PARAM_RAW);
$assessment = optional_param('assessment', 0, PARAM_INT);
$baseindexurl = new moodle_url('/local/excursions/index.php', array());
$indexurl = new moodle_url('/local/excursions/index.php', array(
	'nav' => $nav,
	'status' => $status,
	'campus' => $campus,
	'user' => $user,
	'assessment' => $assessment,
));

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url($indexurl);
$PAGE->set_title(get_string('pluginname', 'local_excursions'));
$PAGE->set_heading(get_string('pluginname', 'local_excursions'));
$PAGE->requires->css(new moodle_url($CFG->wwwroot . '/local/excursions/excursions.css', array('nocache' => rand())));
$PAGE->requires->js_call_amd('local_excursions/index', 'init');

$output = $OUTPUT->header();

// Get events.
$paginaton = locallib::get_events_pagination($nav, 'index');
$filters_status = locallib::get_events_filter_status($status);
$filters_campus = locallib::get_events_filter_campus($campus);
$filters_user = locallib::get_events_filter_user($user);
$events = array();
$isparent = false;
if ($isstaff) {
    $events = eventlib::get_all_events_activities($paginaton->current, $status, $campus, $user, $assessment);
} else {
    $parentactivities = activity::get_for_parent($USER->username);
    $isparent = count($parentactivities);
    $studentactivities = activity::get_for_student($USER->username);
    $activities = array_merge($parentactivities, $studentactivities);
    $events = [];
    foreach ($activities as $activity) {
        $activityexporter = new activity_exporter($activity, array('minimal' => true));
        $events[] = $activityexporter->export($OUTPUT);
    }
}
//echo "<pre>"; var_export($events); exit;
$eventcreateurl = new \moodle_url('/local/excursions/event.php', array());
$eventreviewurl = new \moodle_url('/local/excursions/events.php', array());
$isapprover = count(locallib::get_approver_types($USER->username)) > 0;
$data = array(
    'events' => $events,
    'nav' => $paginaton->nav,
    'filters_status' => $filters_status,
    'filters_campus' => $filters_campus,
    'filters_user' => $filters_user,
    'assessment' => $assessment,
    'hasfilters' => $status || $campus != 'ws' || $user || $assessment,
    'baseurl' => $baseindexurl->out(),
    'eventcreateurl' => $eventcreateurl->out(),
    'eventreviewurl' => $eventreviewurl->out(),
    'isstaff' => $isstaff,
    'isparent' => $isparent,
    'iseventreviewer' => locallib::is_event_reviewer() || $isapprover,
);

//echo "<pre>"; var_export($data); exit;

// Render the announcement list.
$output .= $OUTPUT->render_from_template('local_excursions/index', $data);

// Final outputs.
$output .= $OUTPUT->footer();
echo $output;

