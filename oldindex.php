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

require_login();
$isstaff = locallib::is_cgs_staff();

$sortbyoption = optional_param('sortby', '', PARAM_RAW);
$sortby = $sortbyoption;
$indexurl = new moodle_url('/local/excursions/index.php', array(
	'sortby' => $sortby,
));

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/local/excursions/index.php', array(
	'sortby' => $sortby,
));
$PAGE->set_title(get_string('pluginname', 'local_excursions'));
$PAGE->set_heading(get_string('pluginname', 'local_excursions'));

// If sortby is not specified, check if preference is set.
if (empty($sortby)) {
    $sortby = get_user_preferences('local_excursions_sortby', '');
    if ($sortby) {
        $indexurl->param('sortby', $sortby);
        $PAGE->set_url($indexurl);
    }
} else {
    set_user_preference('local_excursions_sortby', $sortby);
}

$PAGE->requires->css(new moodle_url($CFG->wwwroot . '/local/excursions/excursions.css', array('nocache' => rand())));
$PAGE->requires->js_call_amd('local_excursions/index', 'init');

$output = $OUTPUT->header();

$useractivities = activity::get_for_plannner($USER->username);
$approveractivities = activity::get_for_approver($USER->username, $sortby);
$accompanyingactivities = activity::get_for_accompanying($USER->username);
$parentactivities = activity::get_for_parent($USER->username);
$studentactivities = activity::get_for_student($USER->username);
$primaryschoolactivities = activity::get_for_primary($USER->username);
$seniorschoolactivities = activity::get_for_senior($USER->username);

$relateds = array(
	'useractivities' => $useractivities,
	'approveractivities' => $approveractivities,
	'accompanyingactivities' => $accompanyingactivities,
	'parentactivities' => $parentactivities,
	'studentactivities' => $studentactivities,
	'primaryactivities' => $primaryschoolactivities,
	'senioractivities' => $seniorschoolactivities,
	'isstaff' => $isstaff,
);

$indexexporter = new index_exporter(null, $relateds);
$data = $indexexporter->export($OUTPUT);

//var_export($data); exit;
if ($sortbyoption && $data->has_approveractivities) {
    // If sorting by then we want the approver tab.
    $data->isselected_parentactivities = $data->isselected_studentactivities = $data->isselected_useractivities = 
    $data->isselected_accompanyingactivities = $data->isselected_primaryactivities = $data->isselected_senioractivities = 0;
    $data->isselected_approveractivities = 1;
}

// Render the announcement list.
$output .= $OUTPUT->render_from_template('local_excursions/index', $data);

// Final outputs.
$output .= $OUTPUT->footer();
echo $output;

