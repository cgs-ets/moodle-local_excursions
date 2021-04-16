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
use local_excursions\locallib;

/**
 * The main scheduled task for notifications.
 *
 * @package   local_excursions
 * @copyright 2021 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cron_queue_permissions extends \core\task\scheduled_task {

    // Use the logging trait to get some nice, juicy, logging.
    use \core\task\logging_trait;

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('cron_queue_permissions', 'local_excursions');
    }

    /**
     * Execute the scheduled task.
     */
    public function execute() {
        global $DB;

        // Get unprocessed permission send requests (process max 20 at a time).
        $timenow = time();
        $readabletime = date('Y-m-d H:i:s', $timenow);
        $this->log_start("Fetching unprocessed permission send requests (process max 20 at a time) now ({$readabletime}).");

        $sql = "SELECT *
                  FROM {" . activity::TABLE_EXCURSIONS_PERMISSIONS_SEND . "}
                 WHERE status = 0 
              ORDER BY timecreated ASC";
        $emailactions = $DB->get_records_sql($sql, null, 0, 20);

        // Set the status to 1 (processing).
        $emailactionids = array_column($emailactions, 'id');
        if (empty($emailactionids)) {
            $this->log_finish("No records found. Exiting.");
            return;
        }
        $this->log(sprintf("Found the following records %s. Setting to processing.",
            json_encode($emailactionids),
        ), 2);
        list($in, $params) = $DB->get_in_or_equal($emailactionids);
        $DB->set_field_select(activity::TABLE_EXCURSIONS_PERMISSIONS_SEND, 'status', 1, "id {$in}", $params);

        // Break them out into separate permission records.
        $this->log("Queueing relevant permissions for sending", 2);
        foreach ($emailactions as $emailaction) {
            $students = json_decode($emailaction->studentsjson);
            if (empty($students)) {
                continue;
            }
            foreach ($students as $username) {
                // Queue the permission for sending.
                $sql = "UPDATE {" . activity::TABLE_EXCURSIONS_PERMISSIONS . "}
                           SET queueforsending = 1, queuesendid = ?
                         WHERE activityid = ?
                           AND studentusername = ?";
                $params = array($emailaction->id, $emailaction->activityid, $username);
                $DB->execute($sql, $params);
            }
            $emailaction->status = 2;
            $DB->update_record(activity::TABLE_EXCURSIONS_PERMISSIONS_SEND, $emailaction);
            $this->log(sprintf("Email action %d is complete.",
                $emailaction->id,
            ), 2);
        }

        $this->log_finish("Finished queuing permissions.");
    }

}