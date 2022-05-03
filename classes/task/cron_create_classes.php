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
 * A scheduled task for creating classes for rollmarking.
 *
 * @package   local_excursions
 * @copyright 2022 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_excursions\task;
defined('MOODLE_INTERNAL') || die();

use local_excursions\persistents\activity;
use local_excursions\external\activity_exporter;
use local_excursions\locallib;

/**
 * A scheduled task for creating classes for rollmarking.
 *
 * @package   local_excursions
 * @copyright 2022 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cron_create_classes extends \core\task\scheduled_task {

    // Use the logging trait to get some nice, juicy, logging.
    use \core\task\logging_trait;

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('cron_create_classes', 'local_excursions');
    }

    /**
     * Execute the scheduled task.
     */
    public function execute() {
        global $DB;

        // Find activities that need roll marking.
        $now = time();
        $plusdays = strtotime('+7 day', $now);
        $readablenow= date('Y-m-d H:i:s', $now);
        $readableplusdays= date('Y-m-d H:i:s', $plusdays);
        $this->log_start("Fetching approved activities within the next week (between {$readablenow} and {$readableplusdays}).");
        $activities = activity::get_for_roll_creation($now, $plusdays);
        try {

            $config = get_config('local_excursions');
            $externalDB = \moodle_database::get_driver_instance($config->dbtype, 'native', true);
            $externalDB->connect($config->dbhost, $config->dbuser, $config->dbpass, $config->dbname, '');

            // Get term info.
            $currentterminfo = $externalDB->get_records_sql($config->getterminfosql);
            $currentterminfo =  array_pop($currentterminfo);

            foreach ($activities as $activity) {
                $this->log("Creating class roll for activity " . $activity->get('id'));
                $activitystart = date('Y-m-d H:i', $activity->get('timestart'));
                $activityend = date('Y-m-d H:i', $activity->get('timeend'));

                // 1. Create the class.
                $this->log("Creating the class with staff in charge: " . 'EX_' . $activity->get('id'));
                $sql = $config->createclasssql . ' :fileyear, :filesemester, :classcampus, :classcode, :description, :staffid, :leavingdate, :returningdate';
                $params = array(
                    'fileyear' => $currentterminfo->fileyear,
                    'filesemester' => $currentterminfo->filesemester,
                    'classcampus' => $activity->get('campus') == 'senior' ? 'SEN' : 'PRI',
                    'classcode' => 'EX_' . $activity->get('id'),
                    'description' => $activity->get('activityname'),
                    'staffid' => $activity->get('staffincharge'),
                    'leavingdate' => $activitystart,
                    'returningdate' => $activityend,
                );
                $seqnums = $externalDB->get_records_sql($sql, $params); // Returns staffscheduleseq, subjectclassesseq.
                $seqnums =  array_pop($seqnums);

                // 2. Insert the extra staff.
                $extrastaff = $DB->get_records('excursions_staff', array('activityid' => $activity->get('id')));
                foreach ($extrastaff as $e) {
                    $this->log("Inserting extra class teacher: " . $e->username);
                    $sql = $config->insertclassstaffsql . ' :fileyear, :filesemester, :classcampus, :classcode, :staffid';
                    $params = array(
                        'fileyear' => $currentterminfo->fileyear,
                        'filesemester' => $currentterminfo->filesemester,
                        'classcampus' => $activity->get('campus') == 'senior' ? 'SEN' : 'PRI',
                        'classcode' => 'EX_' . $activity->get('id'),
                        'staffid' => $e->username,
                    );
                    $externalDB->execute($sql, $params);
                }

                // 3. Insert the attending students.
                $attending = activity::get_all_attending($activity->get('id'));
                foreach ($attending as $student) {
                    $this->log("Inserting class student: {$student}.", 2);
                    $sql = $config->insertclassstudentsql . ' :staffscheduleseq, :fileyear, :filesemester, :classcampus, :classcode, :studentid, :subjectclassesseq';
                    $params = array(
                        'staffscheduleseq' => $seqnums->staffscheduleseq,
                        'fileyear' => $currentterminfo->fileyear,
                        'filesemester' => $currentterminfo->filesemester,
                        'classcampus' => $activity->get('campus') == 'senior' ? 'SEN' : 'PRI',
                        'classcode' => 'EX_' . $activity->get('id'),
                        'studentid' => $student,
                        'subjectclassesseq' => $seqnums->subjectclassesseq,
                    );
                    $externalDB->execute($sql, $params);
                }

                // 4. Remove students no longer attending. 
                $studentscsv = implode(',', $attending);
                $this->log("Delete class students not in list: " . $studentscsv, 2);
                $sql = $config->deleteclassstudentssql . ' :classcode, :studentscsv';
                $params = array(
                    'classcode' => 'EX_' . $activity->get('id'),
                    'studentscsv' => implode(',', $attending),
                );
                $externalDB->execute($sql, $params);

                // Mark as processed.
                $activity->set('classrollprocessed', 1);
                $activity->save();
                $this->log("Finished creating class roll for activity " . $activity->get('id'));
            }

            exit;

        } catch (Exception $ex) {
            // Error.
        }

        $this->log_finish("Finished creating class roll");

    }

}