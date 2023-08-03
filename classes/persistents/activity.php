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
 * Provides the {@link local_excursions\persistents\activity} class.
 *
 * @package   local_excursions
 * @copyright 2021 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_excursions\persistents;

defined('MOODLE_INTERNAL') || die();

use local_excursions\external\activity_exporter;
use \local_excursions\forms\form_activity;
use \local_excursions\locallib;
use \core\persistent;
use \core_user;
use \context_user;
use \context_course;

/**
 * Persistent model representing a single activity.
 */
class activity extends persistent {

    /** Table to store this persistent model instances. */
    const TABLE = 'excursions';
    const TABLE_EXCURSIONS_LOGS = 'excursions_logs';
    const TABLE_EXCURSIONS_STUDENTS  = 'excursions_students';
    const TABLE_EXCURSIONS_STUDENTS_TEMP  = 'excursions_students_temp';
    const TABLE_EXCURSIONS_APPROVALS  = 'excursions_approvals';
    const TABLE_EXCURSIONS_COMMENTS = 'excursions_comments';
    const TABLE_EXCURSIONS_PERMISSIONS_SEND = 'excursions_permissions_send';
    const TABLE_EXCURSIONS_PERMISSIONS = 'excursions_permissions';
    const TABLE_EXCURSIONS_STAFF = 'excursions_staff';
    const TABLE_EXCURSIONS_PLANNING_STAFF = 'excursions_planning_staff';
    const TABLE_EXCURSIONS_EVENTS = 'excursions_events';


    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties() {
        return [
            "username" => [
                'type' => PARAM_RAW,
            ],
            "activityname" => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            "activitytype" => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            "campus" => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            "location" => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            "timestart" => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            "timeend" => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            "studentlistjson" => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            "notes" => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            "transport" => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            "cost" => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            "status" => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            "permissions" => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            "permissionstype" => [
                'type' => PARAM_RAW,
                'default' => 0,
            ],
            "permissionslimit" => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            "permissionsdueby" => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            "deleted" => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            "riskassessment" => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            "attachments" => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            "staffincharge" => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            "staffinchargejson" => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            "planningstaffjson" => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            "accompanyingstaffjson" => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            "otherparticipants" => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            "absencesprocessed" => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            "remindersprocessed" => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            "classrollprocessed" => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            "isdraft" => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            "ispastevent" => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
        ];
    }

    /*
    * Function used to process and save data on form submission.
    */
    public static function save_from_data($data) {
        global $DB, $USER;

        // Some validation.
        // Since activities are auto-created, the activity id should always be available.
        if (empty($data->id)) {
            return;
        }

        $context = \context_system::instance();

        // If riskassessment and attachments are not set, it is because non-creators cannot edit these so they do not come through.
        if (isset($data->riskassessment)) {
            // Store risk assessment to a permanent file area.
            file_save_draft_area_files(
                $data->riskassessment, 
                $context->id, 
                'local_excursions', 
                'ra', 
                $data->id, 
                form_activity::ra_options()
            );
        }
        $data->riskassessment = '';
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'local_excursions', 'ra', $data->id, "filename", false);
        foreach ($files as $file) {
            $data->riskassessment .= $file->get_contenthash();
        }

        if (isset($data->attachments)) {
            // Save attachments.
            file_save_draft_area_files(
                $data->attachments, 
                $context->id, 
                'local_excursions', 
                'attachments', 
                $data->id, 
                form_activity::attachment_options()
            );
        }
        $data->attachments = '';
        $files = $fs->get_area_files($context->id, 'local_excursions', 'attachments', $data->id, "filename", false);
        foreach ($files as $file) {
            $data->attachments .= $file->get_contenthash();
        }
        $data->attachments = sha1($data->attachments);

        // Set the staff in charge.
        $data->staffincharge = $USER->username;
        $staffincharge = json_decode($data->staffinchargejson);
        if ($staffincharge) {
            $staffincharge = array_pop($staffincharge);
            $data->staffincharge = $staffincharge->idfield;
        }

        // Update the activity.
        $originalactivity = new static($data->id);
        // Permissions is autosaved independentaly of the form, so it will not be in the formdata.
        $data->permissions = $originalactivity->get('permissions');
        // Set absences flag back to 0 so that absences are cleaned in case of student list change.
        $data->absencesprocessed = 0;
        $data->classrollprocessed = 0;
        // Save the activity.        
        $activity = new static($data->id, $data);
        $activity->save();

        // Add a log entry.
        $log = new \stdClass();
        $log->activityid = $data->id;
        $log->username = $USER->username;
        $log->logtime = $activity->get('timemodified');
        $log->datajson = json_encode($data);
        $DB->insert_record(static::TABLE_EXCURSIONS_LOGS, $log);

        if ($data->activitytype == 'excursion') {
            // Overwrite the planning staff list.
            $DB->delete_records(static::TABLE_EXCURSIONS_PLANNING_STAFF, array('activityid' => $data->id));
            $planningstaff = json_decode($data->planningstaffjson);
            if ($planningstaff) {
                foreach ($planningstaff as $as) {
                    $staff = new \stdClass();
                    $staff->activityid = $data->id;
                    $staff->username = $as->idfield;
                    $DB->insert_record(static::TABLE_EXCURSIONS_PLANNING_STAFF, $staff);
                }
            }

            // Overwrite the accompanying staff list.
            $DB->delete_records(static::TABLE_EXCURSIONS_STAFF, array('activityid' => $data->id));
            $accompanyingstaff = json_decode($data->accompanyingstaffjson);
            if ($accompanyingstaff) {
                foreach ($accompanyingstaff as $as) {
                    $staff = new \stdClass();
                    $staff->activityid = $data->id;
                    $staff->username = $as->idfield;
                    $DB->insert_record(static::TABLE_EXCURSIONS_STAFF, $staff);
                }
            }
        }
        
        // Overwrite the student list.
        $DB->delete_records(static::TABLE_EXCURSIONS_STUDENTS, array('activityid' => $data->id));
        $students = json_decode($data->studentlistjson);
        if ($students) {
            foreach ($students as $username) {
                $student = new \stdClass();
                $student->activityid = $data->id;
                $student->username = $username;
                $DB->insert_record(static::TABLE_EXCURSIONS_STUDENTS, $student);
            }
        }

        // Generate permissions based on student list.
        static::generate_permissions($data->id);

        // If sending for review or saving after already in review, determine the approvers based on student list campuses.
        if ($data->status == locallib::ACTIVITY_STATUS_INREVIEW ||
            $data->status == locallib::ACTIVITY_STATUS_APPROVED) {
            static::generate_approvals($originalactivity, $activity);
        }

        // Update the associated event.
        $event = $DB->get_record('excursions_events', array('activityid' => $data->id));
        if ($event) {
            // Copy values from event.
            $event->activityname = $activity->get('activityname');
            $event->timestart = $activity->get('timestart');
            $event->timeend = $activity->get('timeend');
            $event->campus = 'oncampus';
            if ($activity->get('activitytype') == 'excursion') {
                $event->campus = 'offcampus';
            }
            $event->location = $activity->get('location');
            $event->notes = $activity->get('notes');
            $DB->update_record('excursions_events', $event);
        }

        return $data->id;
    }

    private static function generate_permissions($activityid) {
        global $DB, $USER;

        $activity = new static($activityid);
        if (empty($activity)) {
            return;
        }

        // Generate permissions for saved students.
        $students = static::get_excursion_students($activityid);
        foreach ($students as $student) {
            // Find the student's mentors.
            $user = \core_user::get_user_by_username($student->username);
            if (empty($user)) {
                continue;
            }
            $mentors = locallib::get_users_mentors($user->id);
            foreach ($mentors as $mentor) {
                // Only insert this if it doesn't exist.
                $exists = $DB->record_exists(activity::TABLE_EXCURSIONS_PERMISSIONS, array(
                    'activityid' => $activityid,
                    'studentusername' => $student->username,
                    'parentusername' => $mentor,
                ));

                if (!$exists) {
                    // Create a permissions record for each mentor.
                    $permission = new \stdClass();
                    $permission->activityid = $activityid;
                    $permission->studentusername = $student->username;
                    $permission->parentusername = $mentor;
                    $permission->queueforsending = 0;
                    $permission->queuesendid = 0;
                    $permission->response = 0;
                    $permission->timecreated = time();
                    $DB->insert_record(activity::TABLE_EXCURSIONS_PERMISSIONS, $permission);
                }
            }
        }
    }


    public static function generate_approvals($originalactivity, $newactivity) {
        global $DB, $USER;

        // Check if changed fields cause an approval state to be invalidated.
        $fieldschanged = static::get_changed_fields($originalactivity, $newactivity);
        $fieldschangedkeys = array_keys($fieldschanged);
        foreach (locallib::WORKFLOW as $type => $conf) {
            if (array_intersect($fieldschangedkeys, $conf['invalidated_on_edit'])) {
                $sql = "UPDATE {" . static::TABLE_EXCURSIONS_APPROVALS . "}
                           SET invalidated = 1
                         WHERE activityid = ?
                           AND type = ?
                           AND status > 0";
                $DB->execute($sql, array(
                    $newactivity->get('id'),
                    $type,
                ));
            }
        }

        // Approval stub.
        $approvals = array();
        $approval = new \stdClass();
        $approval->activityid = $newactivity->get('id');
        $approval->username = ''; // The person that eventually approves it.
        $approval->timemodified = time();

        // Workflow.
        switch ($newactivity->get('campus')) {
            case 'senior': {
                // To prevent this from affecting old activites, do not apply to old approved activities. Activities prior to Wednesday, July 21, 2021 9:44:18 AM.
                $ignoreactivity = ($originalactivity->get('status') == locallib::ACTIVITY_STATUS_APPROVED && $originalactivity->get('timecreated') < 1626824658);
                if (!$ignoreactivity) { 
                    // Senior School - 1st approver.
                    $approval->type = 'senior_ra';
                    $approval->sequence = 1;
                    $approval->description = locallib::WORKFLOW['senior_ra']['name'];
                    $approvals[] = clone $approval;
                }

                // Senior School - 2nd approver.
                $approval->type = 'senior_admin';
                $approval->sequence = 2;
                $approval->description = locallib::WORKFLOW['senior_admin']['name'];
                $approvals[] = clone $approval;

                // Senior School - 3st approver.
                $approval->type = 'senior_hoss';
                $approval->sequence = 3;
                $approval->description = locallib::WORKFLOW['senior_hoss']['name'];
                $approvals[] = clone $approval;
                break;
            }
            case 'primary': {

                // To prevent this from affecting old activites, do not apply to old approved activities. Activities prior to Wednesday, Nov 23, 2021 11:18 AM.
                $ignoreactivity = ($originalactivity->get('status') == locallib::ACTIVITY_STATUS_APPROVED && $originalactivity->get('timecreated') < 1637626717);
                if (!$ignoreactivity) { 
                    // Primary School - 1st approver.
                    $approval->type = 'primary_ra';
                    $approval->sequence = 1;
                    $approval->description = locallib::WORKFLOW['primary_ra']['name'];
                    $approvals[] = clone $approval;
                }

                // Primary School - 1st approver.
                $approval->type = 'primary_admin';
                $approval->sequence = 1;
                $approval->description = locallib::WORKFLOW['primary_admin']['name'];
                $approvals[] = clone $approval;

                // Primary School - 2nd approver.
                $approval->type = 'primary_hops';
                $approval->sequence = 2;
                $approval->description = locallib::WORKFLOW['primary_hops']['name'];
                $approvals[] = clone $approval;
                break;
            }
        }

        //echo "<pre>"; var_export($approvals); exit;

        // Invalidate approvals that should not be there.
        $approvaltypes = array_column($approvals, 'type');
        $sql = "UPDATE {" . static::TABLE_EXCURSIONS_APPROVALS . "}
                   SET invalidated = 1
                 WHERE activityid = ?
                   AND invalidated = 0
                   AND type NOT IN ('" . implode("','", $approvaltypes) . "')";
        $params = array($newactivity->get('id'));
        $DB->execute($sql, $params);

        // Insert the approval if it doesn't already exist.
        foreach ($approvals as $approval) {
            $exists = $DB->record_exists(static::TABLE_EXCURSIONS_APPROVALS, array(
                'activityid' => $newactivity->get('id'), 
                'type' => $approval->type, 
                'invalidated' => 0
            ));
            if (!$exists) {
                $DB->insert_record(static::TABLE_EXCURSIONS_APPROVALS, $approval);
            }
        }

        //Update activity status based on current state of approvals.
        static::check_status($newactivity->get('id'), $fieldschanged);

    }

    private static function get_changed_fields($originalactivity, $newactivity) {
        $changed = array();

        // Instaniate an empty copy of the form.
        $form = new form_activity('', array('edit' => $originalactivity->get('id')), 'post');

        foreach (static::properties_definition() as $key => $definition) {
            // skip if one of the meta data.
            if ($key == 'absencesprocessed' || 
                $key == 'remindersprocessed' || 
                $key == 'classrollprocessed' || 
                $key == 'isdraft' || 
                $key == 'ispastevent') {
                continue;
            }
            if ($originalactivity->get($key) != $newactivity->get($key)) {
                $label = $form->get_element_label($key);
                if ($key == "permissionstype") {
                    $label = 'Permission invite type';
                }
                if ($key == "permissionslimit") {
                    $label = 'Permissions limit';
                }
                if ($key == "permissionsdueby") {
                    $label = 'Permission due by date';
                }
                if (empty($label)) {
                    $label = $key;
                }
                $changed[$key] = array(
                    'field' => $key,
                    'label' => $label,
                    'originalval' => $originalactivity->get($key),
                    'newval' => $newactivity->get($key),
                );
            }
        }

        //unset unnecessary fields
        unset($changed['usermodified']);
        unset($changed['timemodified']);

        return $changed;
    }

    public static function search($text) {
        global $DB;

        $sql = "SELECT * 
                    ,case
                        when status = 0 OR status = 1 then 1
                        else 0
                    end as isdraft
                    ,case
                        when timeend < " . time() . " then 1
                        else 0
                    end as ispastevent
                  FROM {" . static::TABLE . "}
                 WHERE deleted = 0
                   AND (activityname LIKE ? OR username LIKE ? OR staffinchargejson LIKE ?)
              ORDER BY isdraft DESC, ispastevent ASC, timestart DESC";
        $params = array();
        $params[] = '%'.$text.'%';
        $params[] = '%'.$text.'%';
        $params[] = '%'.$text.'%';
        //echo "<pre>"; var_export($sql); var_export($params); exit;

        $records = $DB->get_records_sql($sql, $params);
        $activities = array();
        foreach ($records as $record) {
            $activities[] = new static($record->id, $record);
        }

        return $activities;
    }

    public static function get_for_user($username) {
        global $DB;

        $sql = "SELECT * 
                    ,case
                        when status = 0 OR status = 1 then 1
                        else 0
                    end as isdraft
                    ,case
                        when timeend < " . time() . " then 1
                        else 0
                    end as ispastevent
                  FROM {" . static::TABLE . "}
                 WHERE deleted = 0
                   AND username = ?
              ORDER BY isdraft DESC, ispastevent ASC, timestart DESC";
        $params = array($username);

        $records = $DB->get_records_sql($sql, $params);
        $activities = array();
        foreach ($records as $record) {
            $activities[] = new static($record->id, $record);
        }

        return $activities;
    }


    public static function get_for_plannner($username) {
        global $DB;

        $activities = array();

        $sql = "SELECT id
                FROM {" . static::TABLE . "}
                WHERE deleted = 0
                AND ( timemodified > " . strtotime("-3 months") . " OR timeend >=  " . time() . " )
                AND username = ?";
        $useractivities = $DB->get_records_sql($sql, array($username));
        $useractivityids = array_column($useractivities, 'id');

        $sql = "SELECT id, activityid
                    FROM {" . static::TABLE_EXCURSIONS_PLANNING_STAFF. "} 
                    WHERE username = ?";
        $planningstaff = $DB->get_records_sql($sql, array($username));
        $planningids = array_column($planningstaff, 'activityid');
        
        $activities = static::get_by_ids(array_merge($planningids, $useractivityids));

        return $activities;
    }




    public static function get_for_auditor($username) {
        global $DB;

        $user = \core_user::get_user_by_username($username);
        
        if ( ! has_capability('local/excursions:audit', \context_system::instance(), null, false)) {
            return array();
        }

        $sql = "SELECT *
                    ,case
                        when status = 0 OR status = 1 then 1
                        else 0
                    end as isdraft
                    ,case
                        when timeend < " . time() . " then 1
                        else 0
                    end as ispastevent
                  FROM {" . static::TABLE . "}
                 WHERE deleted = 0
                   AND ( timemodified > " . strtotime("-3 months") . " OR timeend >=  " . time() . " )
                   AND status != " . locallib::ACTIVITY_STATUS_AUTOSAVE . "
                   AND status != " . locallib::ACTIVITY_STATUS_DRAFT . "
              ORDER BY isdraft DESC, ispastevent ASC, timestart DESC";
        $records = $DB->get_records_sql($sql, array());
        
        $activities = array();
        foreach ($records as $record) {
            $activities[] = new static($record->id, $record);
        }

        return $activities;
    }

    public static function get_for_parent($username) {
        global $DB;

        $activities = array();

        $sql = "SELECT id, activityid
                  FROM {" . static::TABLE_EXCURSIONS_PERMISSIONS . "} 
                 WHERE parentusername = ?";
        $ids = $DB->get_records_sql($sql, array($username));

        $activities = static::get_by_ids(array_column($ids, 'activityid'), 3); // Approved only.

        return $activities;
    }

    public static function get_for_student($username) {
        global $DB;

        $activities = array();

        $sql = "SELECT id, activityid
                  FROM {" . static::TABLE_EXCURSIONS_STUDENTS . "} 
                 WHERE username = ?";
        $ids = $DB->get_records_sql($sql, array($username));

        $activities = static::get_by_ids(array_column($ids, 'activityid'), 3); // Approved only.
        foreach ($activities as $i => $activity) {
            if ($activity->get('permissions')) {
                $attending = static::get_all_attending($activity->get('id'));
                if (!in_array($username, $attending)) {
                    unset($activities[$i]);
                }
            }
        }

        return array_filter($activities);
    }

    
    public static function get_for_primary($username) {
        global $DB;

        $activities = array();

        // Check if the user is a primary school staff member.
        $user = core_user::get_user_by_username($username);
        profile_load_custom_fields($user);
        $campusroles = strtolower($user->profile['CampusRoles']);
        $userisps = false;
        $primarycampuses = array(
            'Primary School:Admin Staff',
            'Primary Red Hill:Staff',
            'Whole School:Admin Staff',
            'Northside:Staff',
            'Early Learning Centre:Staff',
        );
        foreach ($primarycampuses as $primarycampus) {
            if (strpos($campusroles, strtolower($primarycampus)) !== false) {
                $userisps = true;
                break;
            }
        }

        if ($userisps) {
            // Get activities where campus is 'primary'.
            $sql = "SELECT *
                        ,case
                            when timeend < " . time() . " then 1
                            else 0
                        end as ispastevent
                      FROM {" . static::TABLE . "}
                     WHERE deleted = 0
                       AND ( timemodified > " . strtotime("-3 months") . " OR timeend >=  " . time() . " )
                       AND status = 3
                       AND campus = 'primary'
                  ORDER BY ispastevent ASC, timestart DESC";

            // If auditor...
            if (has_capability('local/excursions:audit', \context_system::instance(), null, false)) {
                $sql = "SELECT *
                            ,case
                                when timeend < " . time() . " then 1
                                else 0
                            end as ispastevent
                        FROM {" . static::TABLE . "}
                        WHERE deleted = 0
                            AND ( timemodified > " . strtotime("-3 months") . " OR timeend >=  " . time() . " )
                            AND status != " . locallib::ACTIVITY_STATUS_AUTOSAVE . "
                            AND status != " . locallib::ACTIVITY_STATUS_DRAFT . "
                            AND campus = 'primary'
                        ORDER BY ispastevent ASC, timestart DESC";
            }
            $records = $DB->get_records_sql($sql);

            $activities = array();
            foreach ($records as $record) {
                $activities[] = new static($record->id, $record);
            }
        }

        return $activities;
    }

    public static function get_for_senior($username) {
        global $DB;

        $activities = array();

        // Check if the user is a primary school staff member.
        $user = core_user::get_user_by_username($username);
        profile_load_custom_fields($user);
        $campusroles = strtolower($user->profile['CampusRoles']);
        $userisss = false;
        $seniorcampuses = array(
            'Senior School:Staff',
            'Whole School:Admin Staff',
        );
        foreach ($seniorcampuses as $seniorcampus) {
            if (strpos($campusroles, strtolower($seniorcampus)) !== false) {
                $userisss = true;
                break;
            }
        }

        if ($userisss) {
            // Get activities where campus is 'senior'.
            $sql = "SELECT *
                        ,case
                            when timeend < " . time() . " then 1
                            else 0
                        end as ispastevent
                      FROM {" . static::TABLE . "}
                     WHERE deleted = 0
                       AND ( timemodified > " . strtotime("-3 months") . " OR timeend >=  " . time() . " )
                       AND status = 3
                       AND campus = 'senior'
                  ORDER BY ispastevent ASC, timestart DESC";
            // If auditor...
            if (has_capability('local/excursions:audit', \context_system::instance(), null, false)) {
                $sql = "SELECT *
                            ,case
                                when timeend < " . time() . " then 1
                                else 0
                            end as ispastevent
                        FROM {" . static::TABLE . "}
                        WHERE deleted = 0
                            AND ( timemodified > " . strtotime("-3 months") . " OR timeend >=  " . time() . " )
                            AND status != " . locallib::ACTIVITY_STATUS_AUTOSAVE . "
                            AND status != " . locallib::ACTIVITY_STATUS_DRAFT . "
                            AND campus = 'senior'
                        ORDER BY ispastevent ASC, timestart DESC";
            }
            $records = $DB->get_records_sql($sql);
            $activities = array();
            foreach ($records as $record) {
                $activities[] = new static($record->id, $record);
            }
        }

        return $activities;
    }

    public static function get_by_ids($ids, $status = null, $orderby = null) {
        global $DB;

        $activities = array();

        if ($ids) {
            $activityids = array_unique($ids);
            list($insql, $inparams) = $DB->get_in_or_equal($activityids);
            $sql = "SELECT *
                        ,case
                            when status = 0 OR status = 1 then 1
                            else 0
                        end as isdraft
                        ,case
                            when timeend < " . time() . " then 1
                            else 0
                        end as ispastevent
                      FROM {" . static::TABLE . "}
                     WHERE id $insql
                       AND deleted = 0
                       ";

            if ($status) {
                $sql .= " AND status = {$status} ";
            }

            if (empty($orderby)) {
                $orderby = 'isdraft DESC, ispastevent ASC, timestart DESC';
            }
            $sql .= " ORDER BY " . $orderby;

            $records = $DB->get_records_sql($sql, $inparams);
            $activities = array();
            foreach ($records as $record) {
                $activities[] = new static($record->id, $record);
            }
        }

        return $activities;
    }

    public static function get_for_approver($username, $sortby = '') {
        global $DB;

        $activities = array();

        $approvertypes = locallib::get_approver_types($username);
        if ($approvertypes) {
            // The user has approver types. Check if any activities need this approval.
            list($insql, $inparams) = $DB->get_in_or_equal($approvertypes);
            $sql = "SELECT id, activityid, type
                      FROM {" . static::TABLE_EXCURSIONS_APPROVALS. "} 
                     WHERE type $insql
                       AND invalidated = 0
                       AND skip = 0";
            $approvals = $DB->get_records_sql($sql, $inparams);
            $approvals = static::filter_approvals_with_prerequisites($approvals);
            $orderby = '';
            if ($sortby == 'created') {
                $orderby = 'timecreated DESC';
            }
            if ($sortby == 'start') {
                $orderby = 'timestart ASC';
            }
            $activities = static::get_by_ids(array_column($approvals, 'activityid'), null, $orderby); 
        }

        return $activities;
    }

    public static function get_for_accompanying($username) {
        global $DB;

        $activities = array();

        $sql = "SELECT id, activityid
                  FROM {" . static::TABLE_EXCURSIONS_STAFF. "} 
                 WHERE username = ?";
        $staff = $DB->get_records_sql($sql, array($username));
        $accompanyingids = array_column($staff, 'activityid');

        $sql = "SELECT id
                  FROM {" . static::TABLE. "} 
                 WHERE staffincharge = ?
                 AND ( timemodified > " . strtotime("-3 months") . " OR timeend >=  " . time() . " )
                 ";
        $staff = $DB->get_records_sql($sql, array($username));
        $staffinchargeids = array_column($staff, 'id');

        $activities = static::get_by_ids(array_merge($accompanyingids, $staffinchargeids));

        return $activities;
    }

    public static function get_for_absences($now, $startlimit, $endlimit) {
        global $DB;

        // Activies must:
        // - be approved.
        // - be unprocessed since the last change.
        // - start within the next two weeks ($startlimit) OR
        // - currently running OR
        // - ended within the past 7 days ($endlimit)  OR
        $sql = "SELECT *
                  FROM {" . static::TABLE . "}
                 WHERE absencesprocessed = 0
                   AND (
                    (timestart <= {$startlimit} AND timestart >= {$now}) OR
                    (timestart <= {$now} AND timeend >= {$now}) OR
                    (timeend >= {$endlimit} AND timeend <= {$now})
                   )
                   AND status = " . locallib::ACTIVITY_STATUS_APPROVED;
        $records = $DB->get_records_sql($sql, null);
        $activities = array();
        foreach ($records as $record) {
            $activities[] = new static($record->id, $record);
        }
        
        return $activities;
    }

    public static function get_for_roll_creation($now, $startlimit) {
        global $DB;

        // Activies must:
        // - be approved.
        // - be unprocessed since the last change.
        // - start within the next x days ($startlimit) OR
        // - currently running OR
        $sql = "SELECT *
                  FROM {" . static::TABLE . "}
                 WHERE classrollprocessed = 0
                   AND (
                    (timestart <= {$startlimit} AND timestart >= {$now}) OR
                    (timestart <= {$now} AND timeend >= {$now})
                   )
                   AND status = " . locallib::ACTIVITY_STATUS_APPROVED;
        $records = $DB->get_records_sql($sql, null);
        $activities = array();
        foreach ($records as $record) {
            $activities[] = new static($record->id, $record);
        }
        
        return $activities;
    }

    public static function get_for_attendance_reminders() {
        global $DB;

        $now = time();
        $sql = "SELECT *
                  FROM {" . static::TABLE . "}
                 WHERE remindersprocessed = 0
                   AND timeend <= {$now}
                   AND status = " . locallib::ACTIVITY_STATUS_APPROVED;
        $records = $DB->get_records_sql($sql, null);
        $activities = array();
        foreach ($records as $record) {
            $activities[] = new static($record->id, $record);
        }
        
        return $activities;
    }


    public static function get_for_approval_reminders($rangestart, $rangeend) {
        global $DB;

        // Activies must:
        // - be unapproved.
        // - starting in x days ($rangestart)
        $sql = "SELECT *
                  FROM {" . static::TABLE . "}
                 WHERE timestart >= {$rangestart} AND timestart <= {$rangeend}
                   AND (
                    status = " . locallib::ACTIVITY_STATUS_DRAFT ." OR 
                    status = " . locallib::ACTIVITY_STATUS_INREVIEW . "
                   )";
        $records = $DB->get_records_sql($sql, null);
        $activities = array();
        foreach ($records as $record) {
            $activities[] = new static($record->id, $record);
        }
        
        return $activities;
    }

    public static function filter_approvals_with_prerequisites($approvals) {
        foreach ($approvals as $i => $approval) {
            // Exlude if waiting for a prerequisite.
            $prerequisites = static::get_prerequisites($approval->activityid, $approval->type);
            if ($prerequisites) {
                unset($approvals[$i]);
            }
        }
        return $approvals;
    }

    public static function get_approval($activityid, $approvalid) {
        global $DB;
        
        $sql = "SELECT *
                  FROM {" . static::TABLE_EXCURSIONS_APPROVALS . "}
                 WHERE activityid = ?
                   AND id = ? 
                   AND invalidated = 0
              ORDER BY sequence ASC";
        $params = array($activityid, $approvalid);

        $records = $DB->get_records_sql($sql, $params);
        $approvals = array();
        foreach ($records as $record) {
            $record->statushelper = locallib::approval_helper($record->status);
            $approvals[] = $record;
        }

        return $approvals;
    }

    public static function get_approvals($activityid) {
        global $DB;
        
        $sql = "SELECT *
                  FROM {" . static::TABLE_EXCURSIONS_APPROVALS . "}
                 WHERE activityid = ?
                   AND invalidated = 0
              ORDER BY sequence ASC";
        $params = array($activityid);

        $records = $DB->get_records_sql($sql, $params);
        $approvals = array();
        foreach ($records as $record) {
            $record->statushelper = locallib::approval_helper($record->status);
            $approvals[] = $record;
        }

        return $approvals;
    }

    public static function get_unactioned_approvals($activityid) {
        global $DB;
        
        $sql = "SELECT *
                  FROM {" . static::TABLE_EXCURSIONS_APPROVALS . "}
                 WHERE activityid = ?
                   AND invalidated = 0
                   AND skip = 0
                   AND status != 1
              ORDER BY sequence ASC";
        $params = array($activityid);

        $records = $DB->get_records_sql($sql, $params);
        $approvals = array();
        foreach ($records as $record) {
            $record->statushelper = locallib::approval_helper($record->status);
            $approvals[] = $record;
        }

        return array_values($approvals);
    }


    public static function is_approver_of_activity($activityid) {
        global $USER, $DB;

        $approvertypes = locallib::get_approver_types();
        if ($approvertypes) {
            // The user is potentially an approver. Check if approver for this activity.
            list($insql, $inparams) = $DB->get_in_or_equal($approvertypes);
            $sql = "SELECT * 
                      FROM {" . static::TABLE_EXCURSIONS_APPROVALS. "} 
                     WHERE type $insql
                       AND activityid = ?";
            $inparams = array_merge($inparams, array($activityid));
            $approvals = $DB->get_records_sql($sql, $inparams);
            if ($approvals) {
                return true;
            }
        }
        
        return false;
    }

    public static function get_notices($activityid, $approvals) {
        global $DB;

        $notices = array();

        // Check to see if activity has existing absences.
        foreach ($approvals as $approval) {
            // There is only a small window when this is available to avoid deletion of new absences.
            // If it is an "admin" approval and user can approve it, and the approval is still 0 and it is not skipped.
            if (strpos($approval->type, 'admin') !== false && 
                isset($approval->canapprove) &&
                $approval->status == 0 &&
                $approval->skip == 0 ) {

                $config = get_config('local_excursions');
                if ($config->checkabsencesql) {
                    $externalDB = \moodle_database::get_driver_instance($config->dbtype, 'native', true);
                    $externalDB->connect($config->dbhost, $config->dbuser, $config->dbpass, $config->dbname, '');
                    $sql = $config->checkabsencesql . ' :username, :leavingdate, :returningdate, :comment';
                    $params = array(
                        'username' => '*',
                        'leavingdate' => '1800-01-01',
                        'returningdate' =>  '9999-01-01',
                        'comment' => '#ID-' . $activityid,
                    );
                    $absenceevents = $externalDB->get_field_sql($sql, $params);
                    if ($absenceevents) {
                        $notices[] = array(
                            'text' => 'Absences exist for previous dates which have since changed in the form. New absences will be added if this activity is approved. Click the icon to delete previous absences created for this activity. Ignore this notice to retain previous absences.', 
                            'action' => 'action-delete-absences',
                            'description' => 'Delete previous absences',
                            'icon' => '<i class="fa fa-trash-o" aria-hidden="true"></i>',
                        );
                    }
                }
                // Don't need to do any more checking.
                break;
            }
        }

        return $notices;
    }

    public static function delete_existing_absences($activityid) {

        if (! (is_int($activityid) && $activityid > 0) ) {
            return false;
        }

        // Some basic security - check if user is an approver in this activity.
        $isapprover = static::is_approver_of_activity($activityid);

        $config = get_config('local_excursions');
        $externalDB = \moodle_database::get_driver_instance($config->dbtype, 'native', true);
        $externalDB->connect($config->dbhost, $config->dbuser, $config->dbpass, $config->dbname, '');
        $sql = $config->deleteabsencessql . ' :leavingdate, :returningdate, :comment, :studentscsv';
        $params = array(
            'leavingdate' => '1800-01-01',
            'returningdate' =>  '9999-01-01',
            'comment' => '#ID-' . $activityid,
            'studentscsv' => '0',
        );
        $externalDB->execute($sql, $params);
        return true;

    }

    /*
    * Save a draft of the activity, used by the auto-save service. At present, the only
    * field auto-saved is the activity name.
    */
    public static function save_draft($formdata) {
        // Some validation.
        if (empty($formdata->id)) {
            return;
        }

        // Save the activity name.
        $activity = new static($formdata->id);
        if ($formdata->activityname) {
            $activity->set('activityname', $formdata->activityname);
            $activity->save();
        }

        return $activity->get('id');
    }
    

    public static function regenerate_student_list($data) {
        global $PAGE, $DB, $OUTPUT;

        $activity = new static($data->activityid);

        // Update the student list for the activity.
        if (empty($activity)) {
            return '';
        }

        $activityexporter = new activity_exporter($activity);
        $output = $PAGE->get_renderer('core');
        $activity = $activityexporter->export($output);

        // Get current student list.
        $existinglist = array_column(static::get_excursion_students_temp($data->activityid), 'username');

        // Only add students that are not already in the list.
        $newstudents = $data->users;
        if ($existinglist) {
            $newstudents = array();
            foreach ($data->users as $username) {
                if (!in_array($username, $existinglist)) {
                    $newstudents[] = $username;
                }
            }
        }

        // Remove the users that are no longer to be in the list.
        $deletefromlist = array_diff($existinglist, $data->users);
        foreach ($deletefromlist as $username) {
            $DB->delete_records(static::TABLE_EXCURSIONS_STUDENTS_TEMP, array(
                'activityid' => $data->activityid,
                'username' => $username,
            ));
        }

        // Insert the new usernames.
        foreach ($newstudents as $username) {
            $record = new \stdClass();
            $record->activityid = $data->activityid;
            $record->username = $username;
            $id = $DB->insert_record(static::TABLE_EXCURSIONS_STUDENTS_TEMP, $record);
        }

        $students = array();
        // Get the students permissions.
        foreach ($data->users as $username) {
            $student = locallib::get_user_display_info($username);
            $student->uptodate = locallib::get_studentdatacheck($username);
            $student->sisconsent = locallib::get_excursionconsent($username);
            $permissions = array_values(static::get_student_permissions($data->activityid, $username));
            $student->permissions = array();
            foreach ($permissions as $permission) {
                $parent = locallib::get_user_display_info($permission->parentusername);
                $parent->response = $permission->response;
                $parent->noresponse = ($permission->response == 0);
                $parent->responseisyes = ($permission->response == 1);
                $parent->responseisno = ($permission->response == 2);
                $student->permissions[] = $parent;
            }
            $students[] = $student;
        }
        usort($students, function($a, $b) {return strcmp($a->fullnamereverse, $b->fullnamereverse);});

        // Generate and return the new students in html rows.
        $data = array(
            'activity' => $activity,
            'students' => $students,
        );

        return $OUTPUT->render_from_template('local_excursions/activityform_studentlist_rows', $data);
    }

    /**
    * Gets all of the activity students.
    *
    * @param int $postid.
    * @return array.
    */
    public static function get_excursion_students($activityid) {
        global $DB;
        $sql = "SELECT *
                  FROM {" . static::TABLE_EXCURSIONS_STUDENTS . "}
                 WHERE activityid = ?";
        $params = array($activityid);
        $students = $DB->get_records_sql($sql, $params);
        return $students;
    }

    /**
    * Gets all of the activity students.
    *
    * @param int $postid.
    * @return array.
    */
    public static function get_excursion_students_temp($activityid) {
        global $DB;
        $sql = "SELECT *
                  FROM {" . static::TABLE_EXCURSIONS_STUDENTS_TEMP . "}
                 WHERE activityid = ?";
        $params = array($activityid);
        $students = $DB->get_records_sql($sql, $params);
        return $students;
    }


    /*
    * Add a comment to an activity.
    */
    public static function post_comment($activityid, $comment) {
        global $USER, $DB;

        if (!static::record_exists($activityid)) {
            return 0;
        }

        // Save the comment.
        $record = new \stdClass();
        $record->username = $USER->username;
        $record->activityid = $activityid;
        $record->comment = $comment;
        $record->timecreated = time();
        $record->id = $DB->insert_record(static::TABLE_EXCURSIONS_COMMENTS, $record);

        static::send_comment_emails($record);

        return $record->id;
    }

    /*
    * Delete a comment
    */
    public static function delete_comment($commentid) {
        global $USER, $DB;

        $DB->delete_records(static::TABLE_EXCURSIONS_COMMENTS, array(
            'id' => $commentid,
            'username' => $USER->username,
        ));

        return 1;
    }

    /*
    * Add a comment to an activity.
    */
    public static function load_comments($activityid) {
        global $USER, $DB, $PAGE, $OUTPUT;

        if (!static::record_exists($activityid)) {
            return 0;
        }

        $sql = "SELECT *
                  FROM {" . static::TABLE_EXCURSIONS_COMMENTS . "}
                 WHERE activityid = ?
              ORDER BY timecreated DESC";
        $params = array($activityid);
        $records = $DB->get_records_sql($sql, $params);
        $comments = array();
        foreach ($records as $record) {
            $comment = new \stdClass();
            $comment->id = $record->id;
            $comment->activityid = $record->activityid;
            $comment->username = $record->username;
            $comment->comment = $record->comment;
            $comment->timecreated = $record->timecreated;
            $comment->readabletime = date('g:ia, j M', $record->timecreated);
            $user = \core_user::get_user_by_username($record->username);
            $userphoto = new \user_picture($user);
            $userphoto->size = 2; // Size f2.
            $comment->userphoto = $userphoto->get_url($PAGE)->out(false);
            $comment->userfullname = fullname($user);
            $comment->isauthor = ($comment->username == $USER->username);
            $comments[] = $comment;
        }

        return $OUTPUT->render_from_template('local_excursions/activityform_approvals_comments', array('comments' => $comments));
    }

    /*
    * Save approval
    */
    public static function save_approval($activityid, $approvalid, $checked) {
        global $DB, $USER;

        // Check if user is allowed to do this.
        $isapprover = static::is_approver_of_activity($activityid);
        if ($isapprover) {
            $userapprovertypes = locallib::get_approver_types($USER->username);
        }

        // Update the approval status.
        list($insql, $inparams) = $DB->get_in_or_equal($userapprovertypes);
        $sql = "UPDATE {" . static::TABLE_EXCURSIONS_APPROVALS . "}
                   SET status = ?, username = ?, timemodified = ?
                 WHERE id = ?
                   AND activityid = ?
                   AND invalidated = 0
                   AND type $insql";
        $params = array($checked, $USER->username, time(), $approvalid, $activityid);
        $params = array_merge($params, $inparams);
        $DB->execute($sql, $params);

        // Check for approval finalisation and return new status.
        $newstatusinfo = static::check_status($activityid, null, true);

        return json_encode($newstatusinfo);
    }

    /*
    * Save skip
    */
    public static function save_skip($activityid, $approvalid, $skip) {
        global $DB, $USER;

        // Check if user is allowed to do this.
        $isapprover = static::is_approver_of_activity($activityid);

        // Update the approval status.
        $sql = "UPDATE {" . static::TABLE_EXCURSIONS_APPROVALS . "}
                   SET skip = ?, username = ?, timemodified = ?
                 WHERE id = ?
                   AND activityid = ?
                   AND invalidated = 0";
        $params = array($skip, $USER->username, time(), $approvalid, $activityid);
        $DB->execute($sql, $params);

        // Check for approval finalisation and return new status.
        $newstatusinfo = static::check_status($activityid, null, true);

        return json_encode($newstatusinfo);
    }

    /*
    * Nominate Approver
    */
    public static function nominate_approver($activityid, $approvalid, $nominated) {
        global $DB, $USER;

        // Check if user is allowed to do this.
        $isapprover = static::is_approver_of_activity($activityid);
        
        $activity = new static($activityid);

        // Update the approval.
        $sql = "UPDATE {" . static::TABLE_EXCURSIONS_APPROVALS . "}
                   SET nominated = ?, timemodified = ?
                 WHERE id = ?
                   AND activityid = ?
                   AND invalidated = 0";
        $params = array($nominated, time(), $approvalid, $activityid);
        $DB->execute($sql, $params);

        // Send the notification.
        $approvals = static::get_approval($activityid, $approvalid);
        foreach ($approvals as $approval) {
            $approver = locallib::WORKFLOW[$approval->type]['approvers'][$nominated];
            if ($approver['contacts']) {
                foreach ($approver['contacts'] as $email) {
                    static::send_next_approval_email($activity, locallib::WORKFLOW[$approval->type]['name'], $nominated, $email, [$USER->email]);
                }
            } else {
                static::send_next_approval_email($activity, locallib::WORKFLOW[$approval->type]['name'], $nominated, null, [$USER->email]);
            }
        }

        // Check for approval finalisation and return new status.
        return json_encode(['status' => 'complete']);
    }

    /*
    * Enable permissions
    */
    public static function enable_permissions($activityid, $checked) {
        $activity = new static($activityid);
        $activity->set('permissions', $checked);
        $activity->save();
    }

    /*
    * Send permissions
    */
    public static function send_permissions($activityid, $limit, $dueby, $users, $extratext) {
        global $USER, $DB;

        // Convert due by json array to timestamp.
        $dueby = json_decode($dueby);
        $duebystring = "{$dueby[2]}-{$dueby[1]}-{$dueby[0]} {$dueby[3]}:{$dueby[4]}"; // Format yyyy-m-d h:m.
        $dueby = strtotime($duebystring);

        if (empty($limit)) {
            $limit = 0;
        }

        // Save due by and limit.
        $activity = new static($activityid);
        $activity->set('permissionstype', 'system');
        if ($limit) {
            $activity->set('permissionslimit', $limit);
        }
        if ($dueby) {
            $activity->set('permissionsdueby', $dueby);
        }
        $activity->save();

        // Queue an email.
        $rec = new \stdClass();
        $rec->activityid = $activityid;
        $rec->username = $USER->username;
        $rec->studentsjson = $users;
        $rec->extratext = $extratext;
        $rec->timecreated = time();
        $DB->insert_record(static::TABLE_EXCURSIONS_PERMISSIONS_SEND, $rec);
    }

    /*
    * Save approval
    */
    public static function check_status($activityid, $fieldschanged = null, $progressed = false) {
        global $DB, $PAGE, $OUTPUT;

        // Check for remaining approvals and set activity status based on findings.
        $remainingapprovals = static::get_unactioned_approvals($activityid);
        $activity = new static($activityid);
        $oldstatus = locallib::status_helper($activity->get('status'));
        $status = locallib::ACTIVITY_STATUS_INREVIEW;
        if (empty($remainingapprovals)) {
            // No unapproved.
            $status = locallib::ACTIVITY_STATUS_APPROVED;
        }
        $activity->set('status', $status);
        $activity->save();
        $newstatus = locallib::status_helper($status);

        // Send emails depending on status change.
        // Approver needs to be notified when:
        // - Draft to in review
        // - In review to in review
        // - Approved back to in review
        // - Basically whenever the new status is in review.
        if ($newstatus->inreview) {
            static::notify_next_approver($activityid);
        }

        // Creator needs to be notified whenever there is a status change.
        // Going from draft to in-review, approved back to in-review.
        if ($oldstatus->status != $newstatus->status && !$newstatus->isapproved) {
            static::send_activity_status_email($activityid, $oldstatus, $newstatus);
        }

        // Send workflow progressed email.
        if ($oldstatus->inreview && $newstatus->inreview && $progressed) {
            static::send_workflow_email($activityid);
        }

        // Send approved status email.
        if ($oldstatus->inreview && $newstatus->isapproved) {
            static::send_approved_emails($activityid);
        }

        // If changes after already approved, send email to relevant staff.
        if ($fieldschanged) {
            if ($oldstatus->isapproved && $newstatus->isapproved) {
                static::send_datachanged_emails($activityid, $fieldschanged);
            }
        }

        // Render the html for the overall activity status.
        $statushtml = $OUTPUT->render_from_template('local_excursions/activityform_approvals_status', array('statushelper' => $newstatus));

        // Render the html for the workflow area.
        $activityexporter = new activity_exporter($activity);
        $exported = $activityexporter->export($OUTPUT);
        $workflowhtml = $OUTPUT->render_from_template('local_excursions/activityform_approvals_workflow', $exported);

        return (object) array(
            'status' => $status, 
            'statushtml' => $statushtml,
            'workflowhtml' => $workflowhtml,
        );

    }

    public static function get_prerequisites($activityid, $type) {
        global $DB;

        $prerequisites = locallib::WORKFLOW[$type]['prerequisites'];
        if ($prerequisites) {
            // Check for any yet to be approved.
            list($insql, $inparams) = $DB->get_in_or_equal($prerequisites);
            $sql = "SELECT *
                      FROM {" . activity::TABLE_EXCURSIONS_APPROVALS . "}
                     WHERE activityid = ?
                       AND invalidated = 0
                       AND skip = 0
                       AND type $insql
                       AND status != 1";
            $params = array_merge(array($activityid), $inparams);
            $records = $DB->get_records_sql($sql, $params);
            return $records;
        }
        return null;

    }

    protected static function send_activity_status_email($activityid, $oldstatus, $newstatus) {
        global $USER, $PAGE;

        $activity = new static($activityid);
        $activityexporter = new activity_exporter($activity);
        $output = $PAGE->get_renderer('core');
        $activity = $activityexporter->export($output);

        $toUser = \core_user::get_user_by_username($activity->username);
        $fromUser = \core_user::get_noreply_user();
        $fromUser->bccaddress = array("lms.archive@cgs.act.edu.au"); 

        $data = array(
            'activity' => $activity,
            'oldstatus' => $oldstatus,
            'newstatus' => $newstatus,
        );

        $subject = "Activity status update: " . $activity->activityname;
        $messageText = $output->render_from_template('local_excursions/email_status_text', $data);
        $messageHtml = $output->render_from_template('local_excursions/email_status_html', $data);
        $result = locallib::email_to_user($toUser, $fromUser, $subject, $messageText, $messageHtml, '', '', true); 

    }

    protected static function send_workflow_email($activityid) {
        global $USER, $PAGE;

        $activity = new static($activityid);
        $activityexporter = new activity_exporter($activity);
        $output = $PAGE->get_renderer('core');
        $activity = $activityexporter->export($output);

        $toUser = \core_user::get_user_by_username($activity->username);
        $fromUser = \core_user::get_noreply_user();
        $fromUser->bccaddress = array("lms.archive@cgs.act.edu.au"); 

        $subject = "Activity workflow update: " . $activity->activityname;
        $messageText = $output->render_from_template('local_excursions/email_workflow_text', $activity);
        $messageHtml = $output->render_from_template('local_excursions/email_workflow_html', $activity);
        $result = locallib::email_to_user($toUser, $fromUser, $subject, $messageText, $messageHtml, '', '', true); 

    }

    protected static function notify_next_approver($activityid) {
        $activity = new static($activityid);
        // Get the next approval step.
        $approvals = static::get_unactioned_approvals($activityid);
        $approvals = static::filter_approvals_with_prerequisites($approvals); 
        foreach ($approvals as $nextapproval) {
            $approvers = locallib::WORKFLOW[$nextapproval->type]['approvers'];
            foreach($approvers as $approver) {
                // Skip if approver does not want this notification.
                if (isset($approver['notifications']) && !in_array('approvalrequired', $approver['notifications'])) {
                    continue;
                }
                if ($approver['contacts']) {
                    foreach ($approver['contacts'] as $email) {
                        static::send_next_approval_email($activity, locallib::WORKFLOW[$nextapproval->type]['name'], $approver['username'], $email);
                    }
                } else {
                     static::send_next_approval_email($activity, locallib::WORKFLOW[$nextapproval->type]['name'], $approver['username']);
                }
            }
        }
    }


    protected static function send_next_approval_email($activity, $step = '', $recipient, $email = null, $bccaddressextra = []) {
        global $USER, $PAGE;

        $toUser = \core_user::get_user_by_username($recipient);
        if ($email) {
            // Override the email address.
            $toUser->email = $email;
        }
        $fromUser = \core_user::get_noreply_user();
        $fromUser->bccaddress = array("lms.archive@cgs.act.edu.au"); 
        $fromUser->bccaddress = array_merge($fromUser->bccaddress, $bccaddressextra);

        $activityexporter = new activity_exporter($activity);
        $output = $PAGE->get_renderer('core');
        $activity = $activityexporter->export($output);

        $subject = "Activity approval required [" . $step . "]: " . $activity->activityname;
        $messageText = $output->render_from_template('local_excursions/email_approval_text', $activity);
        $messageHtml = $output->render_from_template('local_excursions/email_approval_html', $activity);


        // Locate the ra and additional files in the Moodle file storage
        $attachments = array();
        $context = \context_system::instance();
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'local_excursions', 'ra', $activity->id, "filename", false);
        foreach ($files as $file) {
            // Copy attachment file to a temporary directory and get the file path.
            $filename = clean_filename($file->get_filename());
            $attachments[$filename] = $file->copy_content_to_temp();
        }
        $files = $fs->get_area_files($context->id, 'local_excursions', 'attachments', $activity->id, "filename", false);
        foreach ($files as $file) {
            $filename = clean_filename($file->get_filename());
            $attachments[$filename] = $file->copy_content_to_temp();
        }

        $result = locallib::email_to_user($toUser, $fromUser, $subject, $messageText, $messageHtml, $attachments, true);

        // Remove an attachment file if any.
        if (!empty($attachments)) {
            foreach ($attachments as $attachment) {
                if (file_exists($attachment)) {
                    unlink($attachment);
                }
            }
        }

    }

    /*
    * Emails the comment to all parties involved. Comments are sent to:
    * - Next approver in line
    * - Approvers that have already actioned approval
    * - Activity creator
    * - Comment poster
    * - Staff in charge
    */
    protected static function send_comment_emails($comment) {
        global $PAGE;

        $activity = new static($comment->activityid);
        $output = $PAGE->get_renderer('core');
        $activityexporter = new activity_exporter($activity);
        $activity = $activityexporter->export($output);

        $recipients = array();

        // Send the comment to the next approver in line.
        $approvals = static::get_unactioned_approvals($comment->activityid);
        foreach ($approvals as $nextapproval) {
            $approvers = locallib::WORKFLOW[$nextapproval->type]['approvers'];
            foreach($approvers as $approver) {
                // Skip if approver does not want this notification.
                if (isset($approver['notifications']) && !in_array('newcomment', $approver['notifications'])) {
                    continue;
                }
                // Check email contacts.
                if ($approver['contacts']) {
                    foreach ($approver['contacts'] as $email) {
                        static::send_comment_email($activity, $comment, $approver['username'], $email);
                        $recipients[] = $approver['username'];
                    }
                } else {
                    if ( ! in_array($approver['username'], $recipients)) {
                        static::send_comment_email($activity, $comment, $approver['username']);
                        $recipients[] = $approver['username'];
                    }
                }
            }
            // Break after sending to next approver in line. Comment is not sent to approvers down stream.
            break;
        }

        // Send comment to approvers that have already actioned an approval for this activity.
        $approvals = static::get_approvals($comment->activityid);
        foreach ($approvals as $approval) {
            if ( ! in_array($approval->username, $recipients)) {

                // Skip if approver does not want this notification.
                $config = locallib::WORKFLOW[$approval->type]['approvers'];
                if (isset($config[$approval->username]) && 
                    isset($config[$approval->username]['notifications']) && 
                    !in_array('newcomment', $config[$approval->username]['notifications'])) {
                        continue;
                }
            
                static::send_comment_email($activity, $comment, $approval->username);
                $recipients[] = $approval->username;
            }
        }

        // Send comment to activity creator.
        if ( ! in_array($activity->username, $recipients)) {
            static::send_comment_email($activity, $comment, $activity->username);
            $recipients[] = $activity->username;
        }

        // Send comment to the comment poster if they are not one of the above.
        if ( ! in_array($USER->username, $recipients)) {
            static::send_comment_email($activity, $comment, $USER->username);
            $recipients[] = $USER->username;
        }

        // Send to staff in charge.
        if ( ! in_array($activity->staffincharge, $recipients)) {
            static::send_comment_email($activity, $comment, $activity->staffincharge);
            $recipients[] = $activity->staffincharge;
        }

    }

    protected static function send_comment_email($activity, $comment, $recipient, $email = null) {
        global $USER, $PAGE;

        $toUser = \core_user::get_user_by_username($recipient);
        if ($email) {
            // Override the email address.
            $toUser->email = $email;
        }

        $data = array(
            'user' => $USER,
            'activity' => $activity,
            'comment' => $comment,
        );

        $subject = "Comment re: " . $activity->activityname;
        $output = $PAGE->get_renderer('core');
        $messageText = $output->render_from_template('local_excursions/email_comment_text', $data);
        $messageHtml = $output->render_from_template('local_excursions/email_comment_html', $data);
        $result = email_to_user($toUser, $USER, $subject, $messageText, $messageHtml, '', '', true);
    }

    protected static function send_approved_emails($activityid) {
        global $PAGE;

        $activity = new static($activityid);
        $output = $PAGE->get_renderer('core');

        $recipients = array();

        // Send to all approvers.
        $approvals = static::get_approvals($activityid);
        foreach ($approvals as $nextapproval) {
            $approvers = locallib::WORKFLOW[$nextapproval->type]['approvers'];
            foreach($approvers as $approver) {
                // Skip if approver does not want this notification.
                if (isset($approver['notifications']) && !in_array('activityapproved', $approver['notifications'])) {
                    continue;
                }
                $usercontext = \core_user::get_user_by_username($approver['username']);
                $relateds = array('usercontext' => $usercontext);
                $activityexporter = new activity_exporter($activity, $relateds);
                $exported = $activityexporter->export($output);
                if ($approver['contacts']) {
                    foreach ($approver['contacts'] as $email) {
                        // Export each time as user context is needed to determine creator etc.
                        static::send_approved_email($exported, $approver['username'], $email);
                        $recipients[] = $approver['username'];
                    }
                } else {
                    if ( ! in_array($approver['username'], $recipients)) {
                        static::send_approved_email($exported, $approver['username']);
                        $recipients[] = $approver['username'];
                    }
                }
            }
        }

        // Send to planning staff.
        $planningstaff = static::get_planning_staff($activityid);
        foreach ($planningstaff as $staff) {
            if ( ! in_array($staff->username, $recipients)) {
                $usercontext = \core_user::get_user_by_username($staff->username);
                $relateds = array('usercontext' => $usercontext);
                $activityexporter = new activity_exporter($activity, $relateds);
                $exported = $activityexporter->export($output);
                static::send_approved_email($exported, $staff->username);
                $recipients[] = $staff->username;
            }
        }

        // Send to accompanying staff.
        $accompanyingstaff = static::get_accompanying_staff($activityid);
        foreach ($accompanyingstaff as $staff) {
            if ( ! in_array($staff->username, $recipients)) {
                $usercontext = \core_user::get_user_by_username($staff->username);
                $relateds = array('usercontext' => $usercontext);
                $activityexporter = new activity_exporter($activity, $relateds);
                $exported = $activityexporter->export($output);
                static::send_approved_email($exported, $staff->username);
                $recipients[] = $staff->username;
            }
        }

        // Send to activity creator.
        if ( ! in_array($activity->get('username'), $recipients)) {
            $usercontext = \core_user::get_user_by_username($activity->get('username'));
            $relateds = array('usercontext' => $usercontext);
            $activityexporter = new activity_exporter($activity, $relateds);
            $exported = $activityexporter->export($output);
            static::send_approved_email($exported, $exported->username);
            $recipients[] = $exported->username;
        }

        // Send to staff in charge.
        if ( ! in_array($activity->get('staffincharge'), $recipients)) {
            $usercontext = \core_user::get_user_by_username($activity->get('staffincharge'));
            $relateds = array('usercontext' => $usercontext);
            $activityexporter = new activity_exporter($activity, $relateds);
            $exported = $activityexporter->export($output);
            static::send_approved_email($exported, $exported->staffincharge);
            $recipients[] = $exported->staffincharge;
        }
    }

    protected static function send_approved_email($activity, $recipient, $email = '') {
        global $USER, $PAGE;

        $toUser = \core_user::get_user_by_username($recipient);
        if ($email) {
            // Override the email address.
            $toUser->email = $email;
        }

        $fromUser = \core_user::get_noreply_user();
        $fromUser->bccaddress = array("lms.archive@cgs.act.edu.au"); 

        $data = array(
            'activity' => $activity,
        );

        $subject = "Activity approved: " . $activity->activityname;
        $output = $PAGE->get_renderer('core');
        $messageText = $output->render_from_template('local_excursions/email_approved_text', $data);
        $messageHtml = $output->render_from_template('local_excursions/email_approved_html', $data);
        $result = locallib::email_to_user($toUser, $fromUser, $subject, $messageText, $messageHtml, '', '', true); 
    }

    protected static function send_datachanged_emails($activityid, $fieldschanged) {
        global $PAGE;

        $activity = new static($activityid);
        $output = $PAGE->get_renderer('core');
        $activityexporter = new activity_exporter($activity);
        $activity = $activityexporter->export($output);
        $activity->fieldschanged = array_values($fieldschanged); // Inject fields changed for emails.
        $activity->fieldschangedstring = json_encode($fieldschanged); // Inject fields changed for emails.

        $recipients = array();

        // Send to all approvers.
        $approvals = static::get_approvals($activityid);
        foreach ($approvals as $nextapproval) {
            $approvers = locallib::WORKFLOW[$nextapproval->type]['approvers'];
            foreach($approvers as $approver) {
                // Skip if approver does not want this notification.
                if (isset($approver['notifications']) && !in_array('activitychanged', $approver['notifications'])) {
                    continue;
                }
                if ($approver['contacts']) {
                    foreach ($approver['contacts'] as $email) {
                        static::send_datachanged_email($activity, $approver['username'], $email);
                        $recipients[] = $approver['username'];
                    }
                } else {
                    if ( ! in_array($approver['username'], $recipients)) {
                        static::send_datachanged_email($activity, $approver['username']);
                        $recipients[] = $approver['username'];
                    }
                }
            }
        }

        // Send to accompanying staff.
        $planningstaff = static::get_planning_staff($activityid);
        foreach ($planningstaff as $staff) {
            if ( ! in_array($staff->username, $recipients)) {
                static::send_datachanged_email($activity, $staff->username);
                $recipients[] = $staff->username;
            }
        }

        // Send to accompanying staff.
        $accompanyingstaff = static::get_accompanying_staff($activityid);
        foreach ($accompanyingstaff as $staff) {
            if ( ! in_array($staff->username, $recipients)) {
                static::send_datachanged_email($activity, $staff->username);
                $recipients[] = $staff->username;
            }
        }

        // Send to activity creator.
        if ( ! in_array($activity->username, $recipients)) {
            static::send_datachanged_email($activity, $activity->username);
            $recipients[] = $activity->username;
        }

        // Send to staff in charge.
        if ( ! in_array($activity->staffincharge, $recipients)) {
            static::send_datachanged_email($activity, $activity->staffincharge);
            $recipients[] = $activity->staffincharge;
        }
    }

    protected static function send_datachanged_email($activity, $recipient, $email = '') {
        global $USER, $PAGE;

        $toUser = \core_user::get_user_by_username($recipient);
        if ($email) {
            // Override the email address.
            $toUser->email = $email;
        }

        $fromUser = \core_user::get_noreply_user();
        $fromUser->bccaddress = array("lms.archive@cgs.act.edu.au"); 

        $subject = "Activity information changed: " . $activity->activityname;
        $output = $PAGE->get_renderer('core');
        $messageText = $output->render_from_template('local_excursions/email_datachanged_text', $activity);
        $messageHtml = $output->render_from_template('local_excursions/email_datachanged_html', $activity);
        $result = locallib::email_to_user($toUser, $fromUser, $subject, $messageText, $messageHtml, '', '', true); 
    }

    public static function get_messagehistory($activityid) {
        global $DB;

        $activity = new static($activityid);
        if (empty($activity)) {
            return [];
        }

        $sql = "SELECT *
                  FROM {" . static::TABLE_EXCURSIONS_PERMISSIONS_SEND . "}
                 WHERE activityid = ?
              ORDER BY timecreated DESC";
        $params = array($activityid);
        $messagehistory = $DB->get_records_sql($sql, $params);

        return $messagehistory;
    }

    public static function get_messagehistory_html($activityid) {
        global $PAGE;

        $activity = new static($activityid);
        $activityexporter = new activity_exporter($activity);
        $output = $PAGE->get_renderer('core');
        $activity = $activityexporter->export($output);
        return $output->render_from_template('local_excursions/activityform_studentlist_messagehistory', $activity);
    }

    public static function get_all_permissions($activityid) {
        global $USER, $DB;

        $sql = "SELECT DISTINCT p.*
                  FROM {" . static::TABLE_EXCURSIONS_PERMISSIONS . "} p
            INNER JOIN {" . static::TABLE_EXCURSIONS_STUDENTS . "} s ON p.studentusername = s.username
                 WHERE p.activityid = ?
              ORDER BY p.timecreated DESC";
        $params = array($activityid);
        $permissions = $DB->get_records_sql($sql, $params);

        return $permissions;
    }

    /*
    * A "no" response means the student is not attending, even if another parent response "yes"
    */
    public static function get_all_attending($activityid) {
        global $USER, $DB;

        $attending = array();

        $activity = new static($activityid);
        if ($activity->get('permissions')) {
            $sql = "SELECT DISTINCT p.studentusername
                      FROM {" . static::TABLE_EXCURSIONS_PERMISSIONS . "} p
                INNER JOIN {" . static::TABLE_EXCURSIONS_STUDENTS . "} s ON p.studentusername = s.username
                     WHERE p.activityid = ?
                       AND p.response = 1
                       AND p.studentusername NOT IN ( 
                           SELECT studentusername
                             FROM mdl_excursions_permissions
                            WHERE activityid = ?
                              AND response = 2
                       )";
            $params = array($activityid, $activityid);
            $attending = $DB->get_records_sql($sql, $params);
            $attending = array_values(array_column($attending, 'studentusername'));
        } else {
            $attending = static::get_excursion_students($activityid);
            $attending = array_values(array_column($attending, 'username'));
        }

        return $attending;
    }

    public static function get_parent_permissions($activityid, $parentusername) {
        global $DB;

        $sql = "SELECT DISTINCT p.*
                  FROM {" . static::TABLE_EXCURSIONS_PERMISSIONS . "} p
            INNER JOIN {" . static::TABLE_EXCURSIONS_STUDENTS . "} s ON p.studentusername = s.username
                 WHERE p.activityid = ?
                   AND p.parentusername = ?
              ORDER BY p.timecreated DESC";
        $params = array($activityid, $parentusername);
        $permissions = $DB->get_records_sql($sql, $params);

        return $permissions;
    }

    public static function get_student_permissions($activityid, $studentusername) {
        global $DB;

        $sql = "SELECT DISTINCT p.*
                  FROM {" . static::TABLE_EXCURSIONS_PERMISSIONS . "} p
            INNER JOIN {" . static::TABLE_EXCURSIONS_STUDENTS . "} s ON p.studentusername = s.username
                 WHERE p.activityid = ?
                   AND p.studentusername = ?
              ORDER BY p.timecreated DESC";
        $params = array($activityid, $studentusername);
        $permissions = $DB->get_records_sql($sql, $params);

        return $permissions;
    }

    public static function get_students_by_response($activityid, $response) {
        global $DB;

        $sql = "SELECT DISTINCT p.studentusername
                  FROM {" . static::TABLE_EXCURSIONS_PERMISSIONS . "} p
            INNER JOIN {" . static::TABLE_EXCURSIONS_STUDENTS . "} s ON p.studentusername = s.username
                 WHERE p.activityid = ?
                   AND p.response = ?";
        $params = array($activityid, $response);
        $permissions = $DB->get_records_sql($sql, $params);

        return $permissions;
    }

    /*
    * Save permission
    */
    public static function submit_permission($permissionid, $response) {
        global $DB, $USER;

        $activityid = $DB->get_field(static::TABLE_EXCURSIONS_PERMISSIONS, 'activityid', array('id' => $permissionid));
        $activity = new static($activityid);
        
        // Check if past permissions dueby or limit.
        $permissionshelper = locallib::permissions_helper($activity->get('id'));

        if ($permissionshelper->activitystarted || $permissionshelper->ispastdueby || $permissionshelper->ispastlimit) {
            return;
        }

        // Update the permission response.
        $sql = "UPDATE {" . static::TABLE_EXCURSIONS_PERMISSIONS . "}
                   SET response = ?, timeresponded = ?
                 WHERE id = ?
                   AND parentusername = ?";
        $params = array($response, time(), $permissionid, $USER->username);
        $DB->execute($sql, $params);

        // Reset absences processed as attendance may have changed due to permission given.
        $activity->set('absencesprocessed', 0);
        $activity->set('classrollprocessed', 0);
        $activity->update();

        // If it is a yes, sent an email to the student to tell them their parent indicated that they will be attending.
        if ($response == '1') {
            static::send_attending_email($permissionid);
        }

        return $response;
    }

    public static function send_attending_email($permissionid) {
        global $DB, $PAGE;

        // Get the permission.
        $permission = $DB->get_record(static::TABLE_EXCURSIONS_PERMISSIONS, array('id' => $permissionid));

        // Get the email users.
        $toUser = \core_user::get_user_by_username($permission->studentusername);
        $fromUser = \core_user::get_noreply_user();
        $fromUser->bccaddress = array("lms.archive@cgs.act.edu.au"); 

        // Get the activity for the permission.
        $activity = new activity($permission->activityid);
        $activityexporter = new activity_exporter($activity);
        $output = $PAGE->get_renderer('core');
        $activity = $activityexporter->export($output);

        // Add additional data for template.
        $parentuser = \core_user::get_user_by_username($permission->parentusername);
        $activity->parentname = fullname($parentuser);
        $activity->studentname = fullname($toUser);


        $messageText = $output->render_from_template('local_excursions/email_attending_text', $activity);
        $messageHtml = $output->render_from_template('local_excursions/email_attending_html', $activity);
        $subject = "Activity: " . $activity->activityname;

        $result = locallib::email_to_user($toUser, $fromUser, $subject, $messageText, $messageHtml, '', '', true);        

    }

    public static function get_planning_staff($activityid) {
        global $DB;
        
        $sql = "SELECT *
                  FROM {" . static::TABLE_EXCURSIONS_PLANNING_STAFF . "}
                 WHERE activityid = ?";
        $params = array($activityid);
        $records = $DB->get_records_sql($sql, $params);

        $staff = array();
        foreach ($records as $record) {
            $staff[] = (object) $record;
        }

        return $staff;
    }

    public static function get_accompanying_staff($activityid) {
        global $DB;
        
        $sql = "SELECT *
                  FROM {" . static::TABLE_EXCURSIONS_STAFF . "}
                 WHERE activityid = ?";
        $params = array($activityid);
        $records = $DB->get_records_sql($sql, $params);

        $staff = array();
        foreach ($records as $record) {
            $staff[] = (object) $record;
        }

        return $staff;
    }

    public static function soft_delete($id) {
        global $DB, $USER;

        $activity = new static($id);
        if (empty($activity)) {
            return;
        }

        // People that can delete.
        $iscreator = ($activity->get('username') == $USER->username);
        $isapprover = static::is_approver_of_activity($id);
        $isstaffincharge = ($activity->get('staffincharge') == $USER->username);


        // Update activity.
        if ($iscreator || $isapprover || $isstaffincharge) {
            // Delete corresponding event.
            $sql = "UPDATE {" . static::TABLE_EXCURSIONS_EVENTS . "}
                    SET deleted = 1
                    WHERE activityid = ?
                    AND isactivity = 1";
            $DB->execute($sql, [$id]);

            // Delete the activity.
            $activity->set('deleted', 1);
            // Reset absences processed so that Synergetic is updated.
            $activity->set('absencesprocessed', 0);
            $activity->set('classrollprocessed', 0);
            $activity->update();

            return 1;
        }

        
    }

}
