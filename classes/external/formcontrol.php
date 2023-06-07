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
 * Provides {@link local_excursions\external\formcontrol} trait.
 *
 * @package   local_excursions
 * @category  external
 * @copyright 2021 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

namespace local_excursions\external;

defined('MOODLE_INTERNAL') || die();

use \local_excursions\persistents\activity;
use \local_excursions\locallib;
use \local_excursions\libs\eventlib;
use external_function_parameters;
use external_value;
use context_user;

require_once($CFG->libdir.'/externallib.php');

/**
 * Trait implementing the external function local_excursions_autosave.
 */
trait formcontrol {

    /**
     * Describes the structure of parameters for the function.
     *
     * @return external_function_parameters
     */
    public static function formcontrol_parameters() {
        return new external_function_parameters([
            'action' =>  new external_value(PARAM_RAW, 'Action'),
            'data' => new external_value(PARAM_RAW, 'Data to process'),
        ]);
    }

    /**
     * Autosave the form data.
     *
     * @param int $query The search query
     */
    public static function formcontrol($action, $data) {
        global $USER;

        // Setup context.
        $context = \context_user::instance($USER->id);
        self::validate_context($context);

        // Validate params.
        self::validate_parameters(self::formcontrol_parameters(), compact('action', 'data'));

        if ($action == 'regenerate_student_list') {
            $data = json_decode($data);
            return activity::regenerate_student_list($data);   
        }

        if ($action == 'get_student_usernames_from_courseid') {
            $courseid = json_decode($data);
            $usernames = locallib::get_student_usernames_from_courseid($courseid);
            return json_encode($usernames);
        }

        if ($action == 'get_student_usernames_from_groupid') {
            $groupid = json_decode($data);
            $usernames = locallib::get_student_usernames_from_groupid($groupid);
            return json_encode($usernames);
        }

        if ($action == 'get_comments') {
            $activityid = json_decode($data);
            return activity::load_comments($activityid);
        }

        if ($action == 'save_approval') {
            $data = json_decode($data);
            return activity::save_approval($data->activityid, $data->approvalid, $data->checked);
        }

        if ($action == 'skip_approval') {
            $data = json_decode($data);
            return activity::save_skip($data->activityid, $data->approvalid, $data->skip);
        }

        if ($action == 'nominate_approver') {
            $data = json_decode($data);
            return activity::nominate_approver($data->activityid, $data->approvalid, $data->nominated);
        }

        if ($action == 'post_comment') {
            $data = json_decode($data);
            return activity::post_comment($data->activityid, $data->comment);
        }

        if ($action == 'delete_comment') {
            $commentid = json_decode($data);
            return activity::delete_comment($commentid);
        }

        if ($action == 'delete_activity') {
            $id = json_decode($data);
            return activity::soft_delete($id);
        }

        if ($action == 'enable_permissions') {
            $data = json_decode($data);
            return activity::enable_permissions($data->activityid, $data->checked);
        }

        if ($action == 'send_permissions') {
            $data = json_decode($data);
            return activity::send_permissions(
                $data->activityid,
                $data->limit,
                $data->dueby,
                $data->users,
                $data->extratext,
            );
        }

        if ($action == 'get_message_history') {
            $activityid = json_decode($data);
            return activity::get_messagehistory_html($activityid);
        }

        if ($action == 'autosave') {
            $activityid = json_decode($data);
            return activity::save_draft($activityid);
        }

        if ($action == 'get_student_selector_data') {
            return locallib::get_student_selector_data();
        }

        if ($action == 'get_student_usernames_from_taglists') {
            $taglists = json_decode($data);
            $usernames = locallib::get_student_usernames_from_taglists($taglists);
            return json_encode($usernames);
        }

        if ($action == 'delete_previous_activities') {
            $activityid = json_decode($data);
            if (activity::delete_existing_absences($activityid)) {
                return 'Previous absences successfully deleted';
            }
        }

        // Event Servies.
        if ($action == 'check_conflicts') {
            $data = json_decode($data);
            return json_encode(eventlib::check_conflicts($data->timestart, $data->timeend));
        }

        if ($action == 'check_conflicts_for_event') {
            return json_encode(eventlib::check_conflicts_for_event($data));
        }
        

        return 1;

    }

    /**
     * Describes the structure of the function return value.
     *
     * @return external_single_structure
     */
    public static function formcontrol_returns() {
         return new external_value(PARAM_RAW, 'Result');
    }

}