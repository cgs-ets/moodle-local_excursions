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
 * @package   local_excursions
 * @copyright 2021 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_excursions;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/filelib.php');

use \local_excursions\external\activity_exporter;
use \local_excursions\persistents\activity;
use \local_excursions\utils;

/**
 * Provides utility functions for this plugin.
 *
 * @package   local_excursions
 * @copyright 2021 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
class generator {

    const BASEURL = '/local/excursions/generator.php';

    public static function make($activityid, $document) {
        global $USER, $DB, $CFG, $PAGE;

        $output = $PAGE->get_renderer('core');

        $exportdir = str_replace('\\\\', '\\', $CFG->dataroot) . '\local_excursions_docs\\';

        // Load the activity.
        $activity = new activity($activityid);
        $activityexporter = new activity_exporter($activity);
        $activity = $activityexporter->export($output);

        if ($document == 'chargesheet') {
            $exportdir = str_replace('\\\\', '\\', $CFG->dataroot) . '\local_excursions_docs\\';

            // Check for the export dir before moving forward.
            if (!is_dir($exportdir)) {
                if (!mkdir($exportdir)) {
                    return array('code' => 'failed', 'data' => 'Failed to create export dir: ' . $exportdir);
                }
            }

            // Get the students.
            $attending = activity::get_all_attending($activityid);
            $students = array();
            foreach ($attending as $username) {
                // Add the student to the list.
                $row = array(
                    'StudentID' => $username,
                    'DebtorID' => '',
                    'FeeCode' => '',
                    'TransactionDate' => date('d/m/Y', $activity->timeend),
                    'TransactionAmount' => $activity->cost,
                    'TransactionDescription' => $activity->activityname,
                );
                $students[] = $row;
            }

            // Create the csv file.
            $filename = 'activity_chargesheet_' . date('Y-m-d-His', time()) . '_' . $activityid . '.csv';
            $path = $exportdir . $filename;

            $fp = fopen($path, 'w');

            // Populate the header fields.
            $header = array(
                'StudentID',
                'DebtorID',
                'FeeCode',
                'TransactionDate',
                'TransactionAmount',
                'TransactionDescription',
            );
            fputcsv($fp, $header);

            // Populate the students.
            foreach ($students as $fields) {
                fputcsv($fp, $fields);
            }

            fclose($fp);

            // Send the file with force download, and don't die so that we can perform cleanup.
            send_file($path, $filename, 10, 0, false, true, 'text/csv', true); //Lifetime is 10 to prevent caching.

            // Delete the zip from the exports folder.
            unlink( $path );
        }

        // Nothing left to do.
        die;

    }

}