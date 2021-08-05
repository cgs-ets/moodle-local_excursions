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
class cron_send_attendance_reminders extends \core\task\scheduled_task {

    // Use the logging trait to get some nice, juicy, logging.
    use \core\task\logging_trait;

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('cron_send_attendance_reminders', 'local_excursions');
    }

    /**
     * Execute the scheduled task.
     */
    public function execute() {
        global $DB, $PAGE;

        $this->log_start("Fetching activities.");
        $activities = activity::get_for_attendance_reminders();

        foreach ($activities as $activity) {
            // Export the activity.
            $activityexporter = new activity_exporter($activity);
            $output = $PAGE->get_renderer('core');
            $data = $activityexporter->export($output);

            // Add staff in charge to list of recipients.
            $recipients = array();
            $recipients[$data->staffincharge] = \core_user::get_user_by_username($data->staffincharge);

            // Add accompanying staff to list of recipients.
            $accompanyingstaff = json_decode($data->accompanyingstaffjson);
            if ($accompanyingstaff) {
                foreach ($accompanyingstaff as $as) {
                    if (!isset($recipients[$as->idfield])) {
                        $recipients[$as->idfield] = \core_user::get_user_by_username($as->idfield);
                    }
                }
            }

            // Send the reminders.
            foreach ($recipients as $recipient) {
                $this->log("Sending reminder for activity " . $data->id . " to " . $recipient->username);
                $this->send_reminder($data, $recipient);
            }
            
            // Mark as processed.
            $activity->set('remindersprocessed', 1);
            $activity->save();
            $this->log("Finished sending reminders for activity " . $data->id);
        }

        $this->log_finish("Finished sending reminders.");
    }

    protected function send_reminder($activity, $recipient) {
        global $DB, $OUTPUT;

        $messagetext = $OUTPUT->render_from_template('local_excursions/email_attendance_reminder_text', $activity);
        $messagehtml = $OUTPUT->render_from_template('local_excursions/email_attendance_reminder_html', $activity);
        $subject = "Confirm student attendance for: " . $activity->activityname;
        $userfrom = \core_user::get_noreply_user();

        $eventdata = new \core\message\message();
        $eventdata->courseid            = SITEID;
        $eventdata->component           = 'local_excursions';
        $eventdata->name                = 'notifications';
        $eventdata->userfrom            = $this->minimise_recipient_record($userfrom);
        $eventdata->userto              = $this->minimise_recipient_record($recipient);
        $eventdata->subject             = $subject;
        $eventdata->fullmessage         = $messagetext;
        $eventdata->fullmessageformat   = FORMAT_PLAIN;
        $eventdata->fullmessagehtml     = $messagehtml;
        $eventdata->notification        = 1;
        message_send($eventdata);

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