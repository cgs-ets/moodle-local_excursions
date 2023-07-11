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
class cron_send_approval_reminders extends \core\task\scheduled_task {

    // Use the logging trait to get some nice, juicy, logging.
    use \core\task\logging_trait;

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('cron_send_approval_reminders', 'local_excursions');
    }

    /**
     * Execute the scheduled task.
     */
    public function execute() {
        global $DB, $PAGE;

        /********************
         * 14 Day Reminder
         */

        $this->log_start("Fetching activities for 14 day reminder.");
        $today = strtotime('today midnight');
        $plus14days = strtotime('+14 day', $today);
        $plus15days = strtotime('+15 day', $today);
        $readableplus14days= date('Y-m-d H:i:s', $plus14days);
        $readableplus15days= date('Y-m-d H:i:s', $plus15days);
        $this->log_start("Fetching unapproved activities starting between {$readableplus14days} and {$readableplus15days}.");
        $activities = activity::get_for_approval_reminders($plus14days, $plus15days);

        foreach ($activities as $activity) {
            // Export the activity.
            $activityexporter = new activity_exporter($activity);
            $output = $PAGE->get_renderer('core');
            $data = $activityexporter->export($output);

            // Add staff in charge to list of recipients.
            $recipients = array();
            $recipients[$data->staffincharge] = null;

            // Send to activity creator.
            if ( ! array_key_exists($data->username, $recipients)) {
                $recipients[$data->username] = null;
            }

            // Send to next approver in line.
            $approvals = activity::get_unactioned_approvals($data->id);
            foreach ($approvals as $nextapproval) {
                $approvers = locallib::WORKFLOW[$nextapproval->type]['approvers'];
                foreach($approvers as $approver) {
                    if ( array_key_exists($approver['username'], $recipients)) {
                        continue;
                    }
                    // Skip if approver does not want any notifications.
                    if (isset($approver['notifications']) && in_array('none', $approver['notifications'])) {
                        continue;
                    }

                    // Check email contacts.
                    if ($approver['contacts']) {
                        foreach ($approver['contacts'] as $email) {
                            $recipients[$approver['username']] = $email;
                        }
                    } else {
                        $recipients[$approver['username']] = null;
                    }
                }
                // Break after sending to next approver in line. Comment is not sent to approvers down stream.
                break;
            }

            // Send the reminders.
            foreach ($recipients as $username => $email) {
                $this->log("Sending reminder for activity " . $data->id . " to " . $username);
                $data->numdays = '14';
                $this->send_reminder($data, $username, $email);
            }
            
            $this->log("Finished sending 14 day reminders for activity " . $data->id);
        }

        /********************
         * 7 Day Reminder
         */

        $this->log_start("Fetching activities for 7 day reminder.");
        $today = strtotime('today midnight');
        $plus7days = strtotime('+7 day', $today);
        $plus8days = strtotime('+8 day', $today);
        $readableplus7days= date('Y-m-d H:i:s', $plus7days);
        $readableplus8days= date('Y-m-d H:i:s', $plus8days);
        $this->log_start("Fetching unapproved activities starting between {$readableplus7days} and {$readableplus8days}.");
        $activities = activity::get_for_approval_reminders($plus7days, $plus8days);

        foreach ($activities as $activity) {
            // Export the activity.
            $activityexporter = new activity_exporter($activity);
            $output = $PAGE->get_renderer('core');
            $data = $activityexporter->export($output);


            // Send to next approver in line.
            $approvals = activity::get_unactioned_approvals($data->id);
            foreach ($approvals as $nextapproval) {
                $approvers = locallib::WORKFLOW[$nextapproval->type]['approvers'];
                foreach($approvers as $approver) {
                    if ( array_key_exists($approver['username'], $recipients)) {
                        continue;
                    }
                    // Skip if approver does not want any notifications.
                    if (isset($approver['notifications']) && in_array('none', $approver['notifications'])) {
                        continue;
                    }

                    // Check email contacts.
                    if ($approver['contacts']) {
                        foreach ($approver['contacts'] as $email) {
                            $recipients[$approver['username']] = $email;
                        }
                    } else {
                        $recipients[$approver['username']] = null;
                    }
                }
                // Break after sending to next approver in line. Comment is not sent to approvers down stream.
                break;
            }

            // Send the reminders.
            foreach ($recipients as $username => $email) {
                $this->log("Sending reminder for activity " . $data->id . " to " . $username);
                $data->numdays = '7';
                $this->send_reminder($data, $username, $email);
            }
            
            $this->log("Finished sending 7 day reminders for activity " . $data->id);
        }





        $this->log_finish("Finished sending reminders.");
    }

    protected function send_reminder($activity, $username, $email) {
        global $OUTPUT;

        $messageText = $OUTPUT->render_from_template('local_excursions/email_approval_reminder_text', $activity);
        $messageHtml = $OUTPUT->render_from_template('local_excursions/email_approval_reminder_html', $activity);
        $subject = "Upcoming activity needs action! " . $activity->activityname;
        $toUser = \core_user::get_user_by_username($username);
        if ($email) {
            // Override the email address.
            $toUser->email = $email;
        }
        $fromUser = \core_user::get_noreply_user();
        $fromUser->bccaddress = array("lms.archive@cgs.act.edu.au"); 
        $result = locallib::email_to_user($toUser, $fromUser, $subject, $messageText, $messageHtml, [], true);
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