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
                $attending = activity::get_all_attending($activity->get('id'));
                if (empty($attending)) {
                    $this->log("Skipping class roll for activity because it has no students in it: " . $activity->get('id'));
                    continue;
                }

                $this->log("Creating class roll for activity " . $activity->get('id'));
                $activitystart = date('Y-m-d H:i', $activity->get('timestart'));
                $activityend = date('Y-m-d H:i', $activity->get('timeend'));

                // If this activity is multiple days, break it into days and create a class for each day so that roll marking works.
                // Convert start and end times to DateTime objects
                $startDateTime = new \DateTime($activitystart);
                $endDateTime = new \DateTime($activityend);

                // Create the result array
                $result = [];

                if ($startDateTime->format('Y-m-d') === $endDateTime->format('Y-m-d')) {
                    // Single-day event
                    $result[] = [
                        "start" => $startDateTime->format('Y-m-d H:i'),
                        "end" => $endDateTime->format('Y-m-d H:i')
                    ];
                } else {
                    // Multiday event
                    $currentDate = clone $startDateTime;

                    while ($currentDate <= $endDateTime) {
                        // For the first day, use the actual start time
                        if ($currentDate == $startDateTime) {
                            $result[] = [
                                "start" => $startDateTime->format('Y-m-d H:i'),
                                "end" => $startDateTime->format('Y-m-d') . ' 23:59'
                            ];
                        } else if ($currentDate->format('Y-m-d') == $endDateTime->format('Y-m-d')) {
                            // For the last day, use the actual end time
                            $result[] = [
                                "start" => $endDateTime->format('Y-m-d') . ' 00:00',
                                "end" => $endDateTime->format('Y-m-d H:i')
                            ];
                        } else {
                            // For middle days, use the whole day
                            $result[] = [
                                "start" => $currentDate->format('Y-m-d') . ' 00:00',
                                "end" => $currentDate->format('Y-m-d') . ' 23:59'
                            ];
                        }

                        // Move to the next day
                        $currentDate->modify('+1 day');
                    }
                }

                // For each day of this event, create a class.
                foreach ($result as $day) {
                    $activitystart = $day['start'];
                    $activityend = $day['end'];
                    // Convert start time to DateTime object
                    $startDateTime = new \DateTime($activitystart);
                    // Format the month and day as MMDD
                    $monthDay = $startDateTime->format('md');
                    $classcode = 'X' . $activity->get('id') . '_' . $monthDay;

                    // 1. Create the class.
                    $this->log("Creating the class " . $classcode . ", with staff in charge " .  $activity->get('staffincharge') . ", start time " .  $activitystart, 2 );
                    $sql = $config->createclasssql . ' :fileyear, :filesemester, :classcampus, :classcode, :description, :staffid, :leavingdate, :returningdate';
                    
                    // Keep within schedule limits.
                    $activitystarthour = date('H', $activity->get('timestart'));
                    if ($activitystarthour < 6) {
                        $activitystart = date('Y-m-d 06:i', $activity->get('timestart'));
                    }
                    if ($activitystarthour > 18 ) {
                        $activitystart = date('Y-m-d 18:i', $activity->get('timestart'));
                    }

                    $params = array(
                        'fileyear' => $currentterminfo->fileyear,
                        'filesemester' => $currentterminfo->filesemester,
                        'classcampus' => $activity->get('campus') == 'senior' ? 'SEN' : 'PRH',
                        'classcode' => $classcode,
                        'description' => $activity->get('activityname'),
                        'staffid' => $activity->get('staffincharge'),
                        'leavingdate' => $activitystart,
                        'returningdate' => $activityend,
                    );
                    $seqnums = $externalDB->get_records_sql($sql, $params); // Returns staffscheduleseq, subjectclassesseq.
                    $seqnums =  array_pop($seqnums);
                    $this->log("The sequence nums (staffscheduleseq, subjectclassesseq): " . json_encode($seqnums), 2);

                    // 2. Insert the extra staff.
                    $extrastaff = $DB->get_records('excursions_staff', array('activityid' => $activity->get('id')));
                    foreach ($extrastaff as $e) {
                        $this->log("Inserting extra class teacher: " . $e->username, 2);
                        $sql = $config->insertclassstaffsql . ' :fileyear, :filesemester, :classcampus, :classcode, :staffid';
                        $params = array(
                            'fileyear' => $currentterminfo->fileyear,
                            'filesemester' => $currentterminfo->filesemester,
                            'classcampus' => $activity->get('campus') == 'senior' ? 'SEN' : 'PRI',
                            'classcode' => $classcode,
                            'staffid' => $e->username,
                        );
                        $externalDB->execute($sql, $params);
                    }

                    // 3. Insert the attending students.
                    foreach ($attending as $student) {
                        $this->log("Inserting class student: {$student}.", 2);
                        $sql = $config->insertclassstudentsql . ' :staffscheduleseq, :fileyear, :filesemester, :classcampus, :classcode, :studentid, :subjectclassesseq';
                        $params = array(
                            'staffscheduleseq' => $seqnums->staffscheduleseq,
                            'fileyear' => $currentterminfo->fileyear,
                            'filesemester' => $currentterminfo->filesemester,
                            'classcampus' => $activity->get('campus') == 'senior' ? 'SEN' : 'PRI',
                            'classcode' => $classcode,
                            'studentid' => $student,
                            'subjectclassesseq' => $seqnums->subjectclassesseq,
                        );
                        $externalDB->execute($sql, $params);
                    }

                    // 4. Remove students no longer attending. 
                    $studentscsv = implode(',', $attending);
                    $this->log("Delete class students not in list: " . $studentscsv, 2);
                    $sql = $config->deleteclassstudentssql . ' :fileyear, :filesemester, :classcampus, :classcode, :studentscsv';
                    $params = array(
                        'fileyear' => $currentterminfo->fileyear,
                        'filesemester' => $currentterminfo->filesemester,
                        'classcampus' => $activity->get('campus') == 'senior' ? 'SEN' : 'PRI',
                        'classcode' => $classcode,
                        'studentscsv' => implode(',', $attending),
                    );
                    $externalDB->execute($sql, $params);
                }

                // Mark as processed.
                $activity->set('classrollprocessed', 1);
                $activity->save();
                $this->log("Finished creating class roll for activity " . $activity->get('id'));
            }

        } catch (Exception $ex) {
            // Error.
        }

        $this->log_finish("Finished creating class roll");

    }

}