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
$isstaff = locallib::is_cgs_staff();

$q = optional_param('q', '', PARAM_RAW);
$indexurl = new moodle_url('/local/excursions/newindex.php');
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/local/excursions/newsearch.php', array('q' => $q,));
$PAGE->set_title(get_string('searchtitle', 'local_excursions') . $q);
$PAGE->set_heading(get_string('searchtitle', 'local_excursions') . $q);
$PAGE->requires->css(new moodle_url($CFG->wwwroot . '/local/excursions/excursions.css', array('nocache' => rand())));
$PAGE->requires->js_call_amd('local_excursions/newindex', 'init');

$output = $OUTPUT->header();

$events = [];
if (!empty($q)) {
    $events = eventlib::search($q);
}


$data = new \stdClass();
$data->events = $events;
$data->q = $q;
$data->indexurl = $indexurl;
$data->isstaff = $isstaff;

// Render the announcement list.
$output .= $OUTPUT->render_from_template('local_excursions/newsearch', $data);

// Final outputs.
$output .= $OUTPUT->footer();
echo $output;

