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
use local_excursions\external\index_exporter;
use local_excursions\locallib;

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/local/excursions/index.php', array());
$PAGE->set_title(get_string('pluginname', 'local_excursions'));
$PAGE->set_heading(get_string('pluginname', 'local_excursions'));

require_login();
$isstaff = locallib::is_cgs_staff();

$PAGE->requires->css(new moodle_url($CFG->wwwroot . '/local/excursions/excursions.css', array('nocache' => rand())));

$output = $OUTPUT->header();

$useractivities = activity::get_for_user($USER->username);
$approveractivities = activity::get_for_approver($USER->username);
$accompanyingactivities = activity::get_for_accompanying($USER->username);
$auditoractivities = activity::get_for_auditor($USER->username);
$parentactivities = activity::get_for_parent($USER->username);
$studentactivities = activity::get_for_student($USER->username);
$primaryschoolactivities = activity::get_for_primary($USER->username);
$seniorschoolactivities = activity::get_for_senior($USER->username);

$relateds = array(
	'useractivities' => $useractivities,
	'approveractivities' => $approveractivities,
	'accompanyingactivities' => $accompanyingactivities,
	'auditoractivities' => $auditoractivities,
	'parentactivities' => $parentactivities,
	'studentactivities' => $studentactivities,
	'primaryschoolactivities' => $primaryschoolactivities,
	'seniorschoolactivities' => $seniorschoolactivities,
	'isstaff' => $isstaff,
);
$indexexporter = new index_exporter(null, $relateds);
$data = $indexexporter->export($OUTPUT);

// Render the announcement list.
$output .= $OUTPUT->render_from_template('local_excursions/index', $data);

// Final outputs.
$output .= $OUTPUT->footer();
echo $output;

