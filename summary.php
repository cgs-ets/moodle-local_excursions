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

$edit = required_param('id', PARAM_INT);

require_login();
locallib::require_cgs_staff();

$summaryurl = new moodle_url('/local/excursions/summary.php', array(
    'id' => $edit,
));
$viewurl = new moodle_url('/local/excursions/index.php');

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url($summaryurl);
$PAGE->set_title(get_string('activitiesetup', 'local_excursions'));
$PAGE->set_heading(get_string('activitiesetup', 'local_excursions'));
$PAGE->navbar->add(get_string('activities', 'local_excursions'), new moodle_url('/local/excursions/index.php'));

// Load existing activity.
$activity = null;
$exists = activity::record_exists($edit);
if ($exists) {
    $activity = new activity($edit);
}

if (!$exists || $activity->get('deleted')) {
    redirect($viewurl->out(false));
    exit;
}

// Export the activity.
$renderer = $PAGE->get_renderer('core');
$activityexporter = new activity_exporter($activity);
$activity = $activityexporter->export($renderer);

// Not using standard moodle footer and header output.
// Add styles.
echo '<link rel="stylesheet" type="text/css" href="' . new moodle_url($CFG->wwwroot . '/local/excursions/excursions.css', array('nocache' => rand())) . '">';
// Some reset css.
echo '<style>body{margin:0;padding:0;}</style>';
// Display activity details
echo $OUTPUT->render_from_template('local_excursions/summary', $activity);

exit;