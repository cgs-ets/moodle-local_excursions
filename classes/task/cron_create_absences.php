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
 * A scheduled task for notifications.
 *
 * @package   local_excursions
 * @copyright 2021 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_excursions\task;
defined('MOODLE_INTERNAL') || die();

use local_excursions\persistents\activity;
use local_excursions\external\activity_exporter;
use local_excursions\locallib;

/**
 * The main scheduled task for notifications.
 *
 * @package   local_excursions
 * @copyright 2021 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cron_create_absences extends \core\task\scheduled_task {

    // Use the logging trait to get some nice, juicy, logging.
    use \core\task\logging_trait;

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('cron_create_absences', 'local_excursions');
    }

    /**
     * Execute the scheduled task.
     */
    public function execute() {
        // Find activities that need to be synced.
        $now = time();
        $plus14days = strtotime('+14 day', $now);
        $minus7days = strtotime('-7 day', $now);
        $readableplus14days= date('Y-m-d H:i:s', $plus14days);
        $readableminus7days = date('Y-m-d H:i:s', $minus7days);
        // Look ahead 2 weeks to find activities starting, look back 1 week to find activities ended
        $this->log_start("Fetching approved activities starting before {$readableplus14days} and finishing after {$readableminus7days}.");
        $activities = activity::get_for_absences($now, $plus14days, $minus7days);
        try {

            $config = get_config('local_excursions');
            $externalDB = \moodle_database::get_driver_instance($config->dbtype, 'native', true);
            $externalDB->connect($config->dbhost, $config->dbuser, $config->dbpass, $config->dbname, '');


            foreach ($activities as $activity) {
                $this->log("Creating absences for activity " . $activity->get('id'));
                $activitystart = date('Y-m-d H:i', $activity->get('timestart'));
                $activityend = date('Y-m-d H:i', $activity->get('timeend'));

                // TODO: If activity time has changed since last time absences were synced we need to wipe all absences before starting the process below.
                // 1. Look for an absence record with this activity id.
                // 2. Compare the dates.
                // 3. If necessary, wipe all the absences.

                // Get list of attending students.
                $attending = activity::get_all_attending($activity->get('id'));
                foreach ($attending as $student) {

                    // Sanity check whether absence already exists for student.
                    $sql = $config->checkabsencesql . ' :username, :leavingdate, :returningdate, :comment';
                    $params = array(
                        'username' => $student,
                        'leavingdate' => $activitystart,
                        'returningdate' => $activityend,
                        'comment' => '#ID-' . $activity->get('id'),
                    );
                    $absenceevents = $externalDB->get_field_sql($sql, $params);
                    if ($absenceevents) {
                        $this->log("Student is already absent during this time. Student: {$student}. Leaving date: {$activitystart}. Returning date: {$activityend}.", 2);
                        continue;
                    }

                    // Insert new absence.
                    $this->log("Creating absence. Student: {$student}. Leaving date: {$activitystart}. Returning date: {$activityend}.", 2);
                    $sql = $config->createabsencesql . ' :username, :leavingdate, :returningdate, :staffincharge, :comment';
                    $params = array(
                        'username' => $student,
                        'leavingdate' => $activitystart,
                        'returningdate' => $activityend,
                        'staffincharge' => $activity->get('staffincharge'),
                        'comment' => $activity->get('activityname') . ' #ID-' . $activity->get('id'),
                    );
                    $externalDB->execute($sql, $params);
                }

                // Delete absences for students no longer attending event.
                $studentscsv = implode(',', $attending);
                $this->log("Delete absences for students not in list: " . $studentscsv, 2);
                $sql = $config->deleteabsencessql . ' :leavingdate, :returningdate, :comment, :studentscsv';
                $params = array(
                    'leavingdate' => $activitystart,
                    'returningdate' => $activityend,
                    'comment' => '#ID-' . $activity->get('id'),
                    'studentscsv' => implode(',', $attending),
                );
                $externalDB->execute($sql, $params);
                $this->log("Deletion complete", 2);

                // Mark as processed.
                $this->log("Setting absencesprocess to 1", 2);
                $activity->set('absencesprocessed', 1);
                $activity->save();
                $this->log("Finished creating absences for activity " . $activity->get('id'));
            }

        } catch (Exception $ex) {
            // Error.
        }

        $this->log_finish("Finished creating absences");

    }

}