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
class cron_send_permissions extends \core\task\scheduled_task {

    // Use the logging trait to get some nice, juicy, logging.
    use \core\task\logging_trait;

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('cron_send_permissions', 'local_excursions');
    }

    /**
     * Execute the scheduled task.
     */
    public function execute() {
        global $DB;

        // Get unsent permissions (max 100 at a time).
        $timenow = time();
        $readabletime = date('Y-m-d H:i:s', $timenow);
        $this->log_start("Fetching unsent permissions (max 100 at a time) now ({$readabletime}).");
        for ($i = 1; $i <= 100; $i++) {
            
            // Get the next unsent permission record.
            $sql = "SELECT *
                      FROM {" . activity::TABLE_EXCURSIONS_PERMISSIONS . "}
                     WHERE queueforsending = 1
                  ORDER BY timecreated ASC";
            $permission = $DB->get_record_sql($sql, null, IGNORE_MULTIPLE);

            if (empty($permission)) {
                $this->log_finish("No more permissions to send. Exiting.");
                return;
            }

            // Immediately mark sent.
            $sql = "UPDATE {" . activity::TABLE_EXCURSIONS_PERMISSIONS . "}
                       SET queueforsending = 0
                     WHERE id = ?";
            $params = array($permission->id);
            $DB->execute($sql, $params);


            // Send the notification.
            $this->log("Found permission " . $permission->id . ". Sending now.");
            $this->send_permission($permission);
        }

        $this->log_finish("Finished sending permissions.");
    }

    protected function send_permission($permission) {
        global $DB, $PAGE, $OUTPUT;

        // Get the activity for the permission.
        $activity = new activity($permission->activityid);
        $activityexporter = new activity_exporter($activity);
        $output = $PAGE->get_renderer('core');
        $activity = $activityexporter->export($output);

        // Get the permission_send record.
        $emailaction = $DB->get_record(activity::TABLE_EXCURSIONS_PERMISSIONS_SEND, array('id' => $permission->queuesendid));

        // Inject some extra things for the template.
        $activity->extratext = $emailaction->extratext;
        $parentuser = \core_user::get_user_by_username($permission->parentusername);
        $studentuser = \core_user::get_user_by_username($permission->studentusername);
        $activity->parentname = fullname($parentuser);
        $activity->studentname = fullname($studentuser);

        $messagetext = $OUTPUT->render_from_template('local_excursions/email_permissions_text', $activity);
        $messagehtml = $OUTPUT->render_from_template('local_excursions/email_permissions_html', $activity);
        $subject = "Permission required for: " . $activity->activityname;
        $userfrom = \core_user::get_noreply_user();

        $eventdata = new \core\message\message();
        $eventdata->courseid            = SITEID;
        $eventdata->component           = 'local_excursions';
        $eventdata->name                = 'notifications';
        $eventdata->userfrom            = $this->minimise_recipient_record($userfrom);
        $eventdata->userto              = $this->minimise_recipient_record($parentuser);
        $eventdata->subject             = $subject;
        $eventdata->fullmessage         = $messagetext;
        $eventdata->fullmessageformat   = FORMAT_PLAIN;
        $eventdata->fullmessagehtml     = $messagehtml;
        $eventdata->notification        = 1;
        message_send($eventdata);

        // Send mobile notification
        //$eventdata->name                = 'notificationsmobile';
        //$eventdata->fullmessage         = 
        //$eventdata->fullmessageformat   = FORMAT_PLAIN;
        //$eventdata->fullmessagehtml     = 
        //message_send($eventdata);

        return true;
    }

    /**
     * Removes properties from user record that are not necessary for sending post notifications.
     *
     */
    protected function minimise_recipient_record($user) {
        // Make sure we do not store info there we do not actually
        // need in mail generation code or messaging.
        unset($user->institution);
        unset($user->department);
        unset($user->address);
        unset($user->city);
        unset($user->url);
        unset($user->currentlogin);
        unset($user->description);
        unset($user->descriptionformat);
        unset($user->icq);
        unset($user->skype);
        unset($user->yahoo);
        unset($user->aim);
        unset($user->msn);
        unset($user->phone1);
        unset($user->phone2);
        unset($user->country);
        unset($user->firstaccess);
        unset($user->lastaccess);
        unset($user->lastlogin);
        unset($user->lastip);

        return $user;
    }

}