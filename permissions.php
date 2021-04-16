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

use \local_excursions\external\activity_exporter;
use \local_excursions\persistents\activity;
use \local_excursions\locallib;

$activityid = required_param('activityid', PARAM_INT);

require_login();

$pageurl = new moodle_url('/local/excursions/permissions.php', array(
    'activityid' => $activityid,
));

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_title(get_string('activitypermissions', 'local_excursions'));
$PAGE->set_heading(get_string('activitypermissions', 'local_excursions'));


// Add css.
$PAGE->requires->css(new moodle_url($CFG->wwwroot . '/local/excursions/excursions.css', array('nocache' => rand())));
// Add scripts.
$PAGE->requires->js_call_amd('local_excursions/permissions', 'init');

$output = $OUTPUT->header();

// Load the activity.
$activity = new activity($activityid);
$activityexporter = new activity_exporter($activity);
$activity = $activityexporter->export($OUTPUT);

$output .= $OUTPUT->render_from_template('local_excursions/permissions', $activity);

$output .= $OUTPUT->footer();

echo $output;
exit;