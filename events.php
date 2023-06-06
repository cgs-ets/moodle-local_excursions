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

use \local_excursions\libs\eventlib;
use local_excursions\locallib;

require_login();
$isstaff = locallib::is_cgs_staff();

$nav = optional_param('nav', '', PARAM_RAW);

$url = new moodle_url('/local/excursions/events.php', array('nav' => $nav));
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_title('Events');
$PAGE->set_heading('Events');

$PAGE->requires->css(new moodle_url($CFG->wwwroot . '/local/excursions/excursions.css', array('nocache' => rand())));
$PAGE->requires->js_call_amd('local_excursions/events', 'init');

$output = $OUTPUT->header();

// Get events.
$paginaton = locallib::get_events_pagination($nav);
$events = [];
if (locallib::is_event_reviewer($paginaton->current)) {
    $events = eventlib::get_all_events();
}
$events = eventlib::get_user_events($paginaton->current);
$data = array(
    'events' => $events,
    'nav' => $paginaton->nav,
);

//echo "<pre>"; var_export($data); exit;

// Render the announcement list.
$output .= $OUTPUT->render_from_template('local_excursions/events', $data);

// Final outputs.
$output .= $OUTPUT->footer();
echo $output;

