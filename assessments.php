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
 * http://moodle4.local/local/excursions/search.php?q=test
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

require_login();
locallib::require_cgs_staff();

$user = optional_param('user', '', PARAM_RAW);
$indexurl = new moodle_url('/local/excursions/index.php');
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/local/excursions/assessments.php', array('user' => $user,));
$PAGE->set_title('Assessments');
$PAGE->set_heading('Assessments');
$PAGE->requires->css(new moodle_url($CFG->wwwroot . '/local/excursions/excursions.css', array('nocache' => rand())));
$PAGE->requires->js_call_amd('local_excursions/assessments', 'init');

$output = $OUTPUT->header();

$filters_user = locallib::get_events_filter_user($user);
$data = new \stdClass();
$data->events = [];
$data->events = eventlib::get_assessments($user);
//echo "<pre>"; var_export($data->events); exit;
$data->user = $user;
$data->indexurl = $indexurl;
$data->issearch = true;
$data->filters_user = $filters_user;


// Render the announcement list.
$output .= $OUTPUT->render_from_template('local_excursions/assessments', $data);

// Final outputs.
$output .= $OUTPUT->footer();
echo $output;

