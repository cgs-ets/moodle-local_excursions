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

require_once($CFG->dirroot . '/local/excursions/config.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');
require_once($CFG->dirroot . '/mod/wiki/diff/difflib.php'); // Use wiki's diff lib.

use local_excursions\persistents\activity;
use \stdClass;

/**
 * Provides utility functions for this plugin.
 *
 * @package   local_excursions
 * @copyright 2021 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
class locallib extends local_excursions_config {

    const ACTIVITY_STATUS_AUTOSAVE = 0;
    const ACTIVITY_STATUS_DRAFT = 1;
    const ACTIVITY_STATUS_INREVIEW = 2;
    const ACTIVITY_STATUS_APPROVED = 3;
    const ACTIVITY_STATUS_CANCELLED = 4;

    const APPROVAL_STATUS_UNAPPROVED = 0;
    const APPROVAL_STATUS_APPROVED = 1;
    const APPROVAL_STATUS_REJECTED = 2;

    public static function permissions_helper($activityid) {
        global $DB;

        $activity = new activity($activityid);
        $type = $activity->get('permissionstype');
        $dueby = $activity->get('permissionsdueby');
        $limit = $activity->get('permissionslimit');

        $permissionshelper = new stdClass();
        $permissionshelper->ismanual = ($type != 'system');
        $permissionshelper->issystem = ($type == 'system');
        $permissionshelper->ispastdueby = false;
        if ($dueby) {
            $permissionshelper->ispastdueby = (time() >= $dueby);
        }
        
        // Get number of approved permissions.
        $permissionshelper->ispastlimit = false;
        if ($limit > 0) {
            $countyes = count(activity::get_students_by_response($activityid, 1));
            $permissionshelper->ispastlimit = ($countyes >= $limit);
        }

        // Check if activity is started.
        $permissionshelper->activitystarted = false;
        if (time() >= $activity->get('timestart')) {
            $permissionshelper->activitystarted = true;
        }

        return $permissionshelper;
    }

    public static function status_helper($status) {
        $statushelper = new stdClass();
        $statushelper->status = $status;
        $statushelper->isautosave = ($status == static::ACTIVITY_STATUS_AUTOSAVE);
        $statushelper->isdraft = ($status == static::ACTIVITY_STATUS_DRAFT);
        $statushelper->inreview = ($status == static::ACTIVITY_STATUS_INREVIEW);
        $statushelper->isapproved = ($status == static::ACTIVITY_STATUS_APPROVED);
        $statushelper->iscancelled = ($status == static::ACTIVITY_STATUS_CANCELLED);
        $statushelper->cansavedraft = $statushelper->isautosave || $statushelper->isdraft || $statushelper->iscancelled;
        return $statushelper;
    }

    public static function approval_helper($status) {
        $approvalhelper = new stdClass();
        $approvalhelper->isactioned = ($status != static::APPROVAL_STATUS_UNAPPROVED);
        $approvalhelper->isapproved = ($status == static::APPROVAL_STATUS_APPROVED);
        $approvalhelper->isrejected = ($status == static::APPROVAL_STATUS_REJECTED);
        return $approvalhelper;
    }

    public static function require_cgs_staff() {
        global $USER;
        
        profile_load_custom_fields($USER);
        $campusroles = strtolower($USER->profile['CampusRoles']);
        if (strpos($campusroles, 'staff') !== false) {
            return true;
        }

        throw new \required_capability_exception(\context_system::instance(), 'local/excursions:manage', 'nopermissions', '');
        exit;
    }

    public static function is_cgs_staff() {
        global $USER;
        
        profile_load_custom_fields($USER);
        $campusroles = strtolower($USER->profile['CampusRoles']);
        if (strpos($campusroles, 'staff') !== false) {
            return true;
        }

        return false;
    }


    public static function get_approver_types($username = null) {
        global $USER;

        if (empty($username)) {
            $username = $USER->username;
        }

        $types = array();
        foreach (static::WORKFLOW as $code => $type) {
            foreach ($type['approvers'] as $approver) {
                if ($approver['username'] == $username) {
                    $types[] = $code;
                }
            }
        }

        return $types;
    }

    /*
    * Search
    *
    * @param string $query. The search query.
    * @param array $types. The type of things to search.
    * @return array of user objects.
    */
    public static function search_recipients($query, $role) {
        global $DB, $USER;


        // Get exact matches first!!
        $sql = "SELECT u.*, 1 AS relevancesort, CONCAT(u.firstname, u.lastname) as fullnamesort
                FROM {user} u
          INNER JOIN {user_info_data} ud ON ud.userid = u.id
          INNER JOIN {user_info_field} uf ON uf.id = ud.fieldid
                WHERE u.username != 'guest' AND u.firstname != '' AND u.lastname != '' 
                  AND (
                    u.username = ? OR 
                    LOWER(CONCAT(u.firstname, ' ', u.lastname)) = ?
                  )
                  AND uf.shortname = 'CampusRoles'
                  AND LOWER(ud.data) LIKE ?";

        $params = array(
            $query,
            strtolower($query),
            '%'.$role.'%',
        );


        $sql .= "

        UNION

        ";

        // Fuzzy matches second.
        $sql .= "SELECT u.*, 2 AS relevancesort, CONCAT(u.firstname, u.lastname) as fullnamesort
                FROM {user} u
          INNER JOIN {user_info_data} ud ON ud.userid = u.id
          INNER JOIN {user_info_field} uf ON uf.id = ud.fieldid
                WHERE u.username != 'guest' AND u.firstname != '' AND u.lastname != '' 
                  AND (
                    LOWER(u.firstname) LIKE ? OR 
                    LOWER(u.lastname) LIKE ? OR
                    ? LIKE CONCAT('%',LOWER(u.firstname),'%') OR
                    ? LIKE CONCAT('%',LOWER(u.lastname),'%'))
                  AND uf.shortname = 'CampusRoles'
                  AND LOWER(ud.data) LIKE ?";

        $params[] = '%'.$DB->sql_like_escape(strtolower($query)).'%';
        $params[] = '%'.$DB->sql_like_escape(strtolower($query)).'%';
        $params[] = strtolower($query);
        $params[] = strtolower($query);
        $params[] = '%'.$role.'%';

        $sql .= "
        ORDER BY relevancesort, fullnamesort";

        $results = array();
        $users = $DB->get_records_sql($sql, $params, 0, 30);

        foreach ($users as $user) {
            $results[] = static::get_recipient_user($user, $query);
        }

        // Return all results.
        return $results;        
    }

    public static function get_recipient_user($user, $searchstr = '') {
        global $PAGE;

        if (!$user) {
            return;
        }

        $userphoto = new \user_picture($user);
        $userphoto->size = 2; // Size f2.
        $fullname = fullname($user);

        $data = array(
            'idfield' => $user->username,
            'fullname' => $fullname,
            'idhighlighted' => static::highlight_word($user->username, $searchstr),
            'fullnamehighlighted' => static::highlight_word($fullname, $searchstr),
            'photourl' => $userphoto->get_url($PAGE)->out(false),
        );

        return $data;
    }

    public static function get_user_display_info($username) {
        global $PAGE;
        $user = \core_user::get_user_by_username($username);
        if (!$user) {
            return;
        }

        $userphoto = new \user_picture($user);
        $userphoto->size = 2; // Size f2.

        $info = new stdClass();
        $info->username = $username;
        $info->fullname = fullname($user);
        $info->fullnamereverse = $user->lastname . ', ' . $user->firstname;
        $info->profilephoto = $userphoto->get_url($PAGE)->out(false);

        return $info;
    }

    public static function get_student_usernames_from_courseid($courseid) {
        global $DB;
        $sql = "SELECT u.username
                  FROM {user} u, {user_enrolments} ue, {enrol} e, {course} c, {role_assignments} ra, {context} cn, {role} r
                 WHERE c.id = ?
                   AND e.courseid = c.id
                   AND ue.enrolid = e.id
                   AND cn.instanceid = c.id
                   AND cn.contextlevel = 50
                   AND u.id = ue.userid
                   AND ra.contextid =  cn.id
                   AND ra.userid = ue.userid
                   AND r.id = ra.roleid
                   AND r.shortname = 'student'";
        $params = array($courseid);
        $students = $DB->get_records_sql($sql, $params);

        return array_column($students, 'username');
    }

    public static function get_student_usernames_from_groupid($groupid) {
        global $DB;
        $sql = "SELECT u.username
                  FROM {user} u, {user_enrolments} ue, {enrol} e, {groups} g, {groups_members} gm, {course} c, {role_assignments} ra, {context} cn, {role} r
                 WHERE g.id = ?
                   AND gm.groupid = g.id
                   AND c.id = g.courseid
                   AND e.courseid = c.id
                   AND ue.enrolid = e.id
                   AND cn.instanceid = c.id
                   AND cn.contextlevel = 50
                   AND u.id = ue.userid
                   AND ra.contextid =  cn.id
                   AND ra.userid = ue.userid
                   AND r.id = ra.roleid
                   AND r.shortname = 'student'
                   AND u.id = gm.userid";
        $params = array($groupid);
        $students = $DB->get_records_sql($sql, $params);

        return array_column($students, 'username');
    }

    public static function highlight_word($content, $word) {
        // return $content if there is no highlight color or strings given, nothing to do.
        if (strlen($content) < 1 || strlen($word) < 1) {
            return $content;
        }
        preg_match_all("/$word+/i", $content, $matches);
        if (is_array($matches[0]) && count($matches[0]) >= 1) {
            // Replace all instances of the first match in the string.
            $content = str_replace($matches[0][0], '<span class="highlighted-text">'.$matches[0][0].'</span>', $content);
        }
        return $content;
    }

    public static function get_users_courses($user) {
        global $DB;

        $out = array();

        // First process courses that the user is enrolled in.
        $courses = enrol_get_users_courses($user->id, true, 'enddate');
        $timenow = time();
        foreach ($courses as $course) {
            // Remove ended courses.
            if ($course->enddate && ($timenow > $course->enddate)) {
                continue;
            }
            $out[] = array(
                'val' => $course->id,
                'txt' => $course->fullname,
            );
        }

        // Next process all other courses.
        $courses = get_courses();
        foreach ($courses as $course) {
            // Skip course if already in list.
            if (in_array($course->id, array_column($out, 'val'))) {
                continue;
            }
            // Remove ended courses.
            if ($course->enddate && ($timenow > $course->enddate)) {
                continue;
            }

            // Get the course category and skip if not a part of Primary or Senior.
            $allowed = false;
            $allowedcats = array(2, 3); // ids of allowed categories. Child categories are also allowed.
            $sql = "SELECT path FROM {course_categories} WHERE id = {$course->category}";
            $catpath = $DB->get_field_sql($sql, null);
            foreach ($allowedcats as $allowedcat) {
                if(preg_match('/\/' . $allowedcat . '(\/|$)/', $catpath)) {
                    $allowed = true;
                    break;
                }
            }
            if (!$allowed) {
                continue;
            }

            $out[] = array(
                'val' => $course->id,
                'txt' => $course->fullname,
            );
        }

        return $out;
    }


    public static function get_users_groups($user) {
        global $DB;

        $out = array();

        $courses = locallib::get_users_courses($user);
        foreach ($courses as $course) {
            // Get the groups in this course.
            $groups = $DB->get_records('groups', array('courseid' => $course['val']));
            foreach ($groups as $group) {
                $out[] = array(
                    'val' => $group->id,
                    'txt' => $course['txt'] . ' > ' . $group->name,
                );
            }
        }
        return $out;
    }


    public static function get_users_mentors($userid) {
        global $DB;

        $mentors = array();
        $mentorssql = "SELECT u.username
                         FROM {role_assignments} ra, {context} c, {user} u
                        WHERE c.instanceid = :menteeid
                          AND c.contextlevel = :contextlevel
                          AND ra.contextid = c.id
                          AND u.id = ra.userid";
        $mentorsparams = array(
            'menteeid' => $userid,
            'contextlevel' => CONTEXT_USER
        );
        if ($mentors = $DB->get_records_sql($mentorssql, $mentorsparams)) {
            $mentors = array_column($mentors, 'username');
        }
        return $mentors;
    }

    public static function get_users_mentees($userid) {
        global $DB;

        // Get mentees for user.
        $mentees = array();
        $menteessql = "SELECT u.username
                         FROM {role_assignments} ra, {context} c, {user} u
                        WHERE ra.userid = :mentorid
                          AND ra.contextid = c.id
                          AND c.instanceid = u.id
                          AND c.contextlevel = :contextlevel";     
        $menteesparams = array(
            'mentorid' => $userid,
            'contextlevel' => CONTEXT_USER
        );
        if ($mentees = $DB->get_records_sql($menteessql, $menteesparams)) {
            $mentees = array_column($mentees, 'username');
        }
        return $mentees;
    }


    public static function get_activitydata_as_xml($data) {
        $xml = "<activityname>{$data->activityname}</activityname>";
        $xml .= "<pypuoi>{$data->pypuoi}</pypuoi>";
        $xml .= "<outcomes>{$data->outcomes}</outcomes>";
        $xml .= "<rubricjson>{$data->rubricjson}</rubricjson>";

        return $xml;
    }

    /* TODO: Uses mod_wikis diff lib */
    public static function diff_versions($json1, $json2) {
        global $DB, $PAGE;
        $olddata = json_decode($json1);
        $newdata = json_decode($json2);

        $oldxml = static::get_activitydata_as_xml($olddata);
        $newxml = static::get_activitydata_as_xml($newdata);

        list($diff1, $diff2) = ouwiki_diff_html($oldxml, $newxml);

        $diff1 = format_text($diff1, FORMAT_HTML, array('overflowdiv'=>true));
        $diff2 = format_text($diff2, FORMAT_HTML, array('overflowdiv'=>true));

        // Mock up the data needed by the wiki renderer.
        $wikioutput = $PAGE->get_renderer('mod_wiki');
        $oldversion = array(
            'id' => 1111, // Use log id.
            'pageid' => $olddata->id,
            'content' => $oldxml,
            'contentformat' => 'html',
            'version' => 1111, // Use log id.
            'timecreated' => 1613693887,
            'userid' => 2,
            'diff' => $diff1,
            'user' => $DB->get_record('user', array('id' => 2)),
        );
        $newversion = array(
            'id' => 1112, // Use log id.
            'pageid' => $newdata->id,
            'content' => $newxml,
            'contentformat' => 'html',
            'version' => 1112, // Use log id.
            'timecreated' => 1613693887,
            'userid' => 2,
            'diff' => $diff2,
            'user' => $DB->get_record('user', array('id' => 2)),
        );

        echo $wikioutput->diff($newdata->id, (object) $oldversion, (object) $newversion, array('total' => 9999));

    }


    public static function get_user_taglists() {
        global $USER;

        try {

            $config = get_config('local_excursions');
            $externalDB = \moodle_database::get_driver_instance($config->dbtype, 'native', true);
            $externalDB->connect($config->dbhost, $config->dbuser, $config->dbpass, $config->dbname, '');

            $sql = $config->usertaglistssql . ' :username';
            $params = array(
                'username' => $USER->username
            );

            $taglists = $externalDB->get_records_sql($sql, $params);

            return array_values($taglists);

        } catch (Exception $ex) {
            // Error.
        }
    }

    public static function get_public_taglists() {
        global $USER;

        try {

            $config = get_config('local_excursions');
            $externalDB = \moodle_database::get_driver_instance($config->dbtype, 'native', true);
            $externalDB->connect($config->dbhost, $config->dbuser, $config->dbpass, $config->dbname, '');

            $sql = $config->publictaglistssql;

            $taglists = $externalDB->get_records_sql($sql);

            return array_values($taglists);

        } catch (Exception $ex) {
            // Error.
        }
    }

    public static function get_student_usernames_from_taglists($taglists) {
        global $DB;

        try {

            $config = get_config('local_excursions');
            $externalDB = \moodle_database::get_driver_instance($config->dbtype, 'native', true);
            $externalDB->connect($config->dbhost, $config->dbuser, $config->dbpass, $config->dbname, '');

            $sql = $config->taglistuserssql . ' :taglistseq';

            $taglistusers = array();

            if ($taglists->user) {
                $params = array(
                    'taglistseq' => $taglists->user,
                );
                $rows = $externalDB->get_records_sql($sql, $params);
                $taglistusers = array_column($rows, 'id');
            }
            
            if ($taglists->public) {
                $params = array(
                    'taglistseq' => $taglists->public,
                );
                $rows = $externalDB->get_records_sql($sql, $params);
                $taglistusers = array_unique(array_merge($taglistusers, array_column($rows, 'id')));
            }

            // Join on mdl_users to ensure they exist as students.
            list($insql, $params) = $DB->get_in_or_equal($taglistusers);
            $sql = "SELECT u.username
                      FROM {user} u
                INNER JOIN {user_info_data} ud ON ud.userid = u.id
                INNER JOIN {user_info_field} uf ON uf.id = ud.fieldid
                     WHERE u.username $insql
                       AND uf.shortname = 'CampusRoles'
                       AND LOWER(ud.data) LIKE '%student%'";
            $students = $DB->get_records_sql($sql, $params);

            return array_column($students, 'username');

        } catch (Exception $ex) {
            // Error.
        }

    }

    public static function get_student_selector_data() {
        global $USER;

        $data = array(
            'courses' => locallib::get_users_courses($USER),
            'groups' => locallib::get_users_groups($USER),
            'taglists' => array(
                'user' => locallib::get_user_taglists(),
                'public' => locallib::get_public_taglists(),
            ),
        );

        return json_encode($data);
    }

    /*
    * @param string $username  Username to check whether student data check has been completed.
    */
    public static function get_studentdatacheck($username) {
        $studentdatacheck = true; // Assume data check done until proven otherwise to prevent false alarms.

        $config = get_config('local_excursions');
        if (empty($config->studentdatachecksql)) {
            return $studentdatacheck;
        }

        try {
            $externalDB = \moodle_database::get_driver_instance($config->dbtype, 'native', true);
            $externalDB->connect($config->dbhost, $config->dbuser, $config->dbpass, $config->dbname, '');

            $sql = $config->studentdatachecksql . ' :username';
            $params = array(
                'username' => 1994
            );

            $result = $externalDB->get_record_sql($sql, $params);
            if ($result) {
                $result = (array) $result;
                $result = reset($result);
                if ($result == 0) {
                    $studentdatacheck = false;
                }
            }
        } catch (Exception $ex) {
            // Error.
        }

        return $studentdatacheck;
    }

    /*
    * @param string $username  Username to check whether general consent is given in routine data collection.
    */
    public static function get_excursionconsent($username) {
        $sisconsent = true; // Assume true until proven otherwise to prevent false alarms.

        $config = get_config('local_excursions');
        if (empty($config->excursionconsentsql)) {
            return $sisconsent;
        }

        try {
            $externalDB = \moodle_database::get_driver_instance($config->dbtype, 'native', true);
            $externalDB->connect($config->dbhost, $config->dbuser, $config->dbpass, $config->dbname, '');

            $sql = $config->excursionconsentsql . ' :username';
            $params = array(
                'username' => $username
            );

            $result = $externalDB->get_record_sql($sql, $params);
            if ($result) {
                $result = (array) $result;
                $result = reset($result);
                if ($result == 0) {
                    $sisconsent = false;
                }
            }
        } catch (Exception $ex) {
            // Error.
        }

        return $sisconsent;
    }


    /**
     * ------------------------------------------------------
     * MV Note: This is a modification of core code. Original code:
     * https://github.com/moodle/moodle/blob/d65ed58e7345bbf0f07f73a49ff39fc9cab0d689/lib/moodlelib.php#L5891
     * Modifications:
     *  - allow for BCC address which core code does not allow.
     *  - allow multiple attachments
     * ------------------------------------------------------
     *
     * Send an email to a specified user
     *
     * @param stdClass $user  A {@link $USER} object
     * @param stdClass $from A {@link $USER} object
     * @param string $subject plain text subject line of the email
     * @param string $messagetext plain text version of the message
     * @param string $messagehtml complete html version of the message (optional)
     * @param string $attachment a file on the filesystem, either relative to $CFG->dataroot or a full path to a file in one of
     *          the following directories: $CFG->cachedir, $CFG->dataroot, $CFG->dirroot, $CFG->localcachedir, $CFG->tempdir
     * @param string $attachname the name of the file (extension indicates MIME)
     * @param bool $usetrueaddress determines whether $from email address should
     *          be sent out. Will be overruled by user profile setting for maildisplay
     * @param string $replyto Email address to reply to
     * @param string $replytoname Name of reply to recipient
     * @param int $wordwrapwidth custom word wrap width, default 79
     * @return bool Returns true if mail was sent OK and false if there was an error.
     */
    public static function email_to_user($user, $from, $subject, $messagetext, $messagehtml = '', $attachments = [],
                           $usetrueaddress = true, $replyto = '', $replytoname = '', $wordwrapwidth = 79) {

        global $CFG, $PAGE, $SITE;

        if (empty($user) or empty($user->id)) {
            debugging('Can not send email to null user', DEBUG_DEVELOPER);
            return false;
        }

        if (empty($user->email)) {
            debugging('Can not send email to user without email: '.$user->id, DEBUG_DEVELOPER);
            return false;
        }

        if (!empty($user->deleted)) {
            debugging('Can not send email to deleted user: '.$user->id, DEBUG_DEVELOPER);
            return false;
        }

        if (defined('BEHAT_SITE_RUNNING')) {
            // Fake email sending in behat.
            return true;
        }

        if (!empty($CFG->noemailever)) {
            // Hidden setting for development sites, set in config.php if needed.
            debugging('Not sending email due to $CFG->noemailever config setting', DEBUG_NORMAL);
            return true;
        }

        if (email_should_be_diverted($user->email)) {
            $subject = "[DIVERTED {$user->email}] $subject";
            $user = clone($user);
            $user->email = $CFG->divertallemailsto;
        }

        // Skip mail to suspended users.
        if ((isset($user->auth) && $user->auth=='nologin') or (isset($user->suspended) && $user->suspended)) {
            return true;
        }

        if (!validate_email($user->email)) {
            // We can not send emails to invalid addresses - it might create security issue or confuse the mailer.
            debugging("email_to_user: User $user->id (".fullname($user).") email ($user->email) is invalid! Not sending.");
            return false;
        }

        if (over_bounce_threshold($user)) {
            debugging("email_to_user: User $user->id (".fullname($user).") is over bounce threshold! Not sending.");
            return false;
        }

        // TLD .invalid  is specifically reserved for invalid domain names.
        // For More information, see {@link http://tools.ietf.org/html/rfc2606#section-2}.
        if (substr($user->email, -8) == '.invalid') {
            debugging("email_to_user: User $user->id (".fullname($user).") email domain ($user->email) is invalid! Not sending.");
            return true; // This is not an error.
        }

        // If the user is a remote mnet user, parse the email text for URL to the
        // wwwroot and modify the url to direct the user's browser to login at their
        // home site (identity provider - idp) before hitting the link itself.
        if (is_mnet_remote_user($user)) {
            require_once($CFG->dirroot.'/mnet/lib.php');

            $jumpurl = mnet_get_idp_jump_url($user);
            $callback = partial('mnet_sso_apply_indirection', $jumpurl);

            $messagetext = preg_replace_callback("%($CFG->wwwroot[^[:space:]]*)%",
                    $callback,
                    $messagetext);
            $messagehtml = preg_replace_callback("%href=[\"'`]($CFG->wwwroot[\w_:\?=#&@/;.~-]*)[\"'`]%",
                    $callback,
                    $messagehtml);
        }
        $mail = get_mailer();

        if (!empty($mail->SMTPDebug)) {
            echo '<pre>' . "\n";
        }

        $temprecipients = array();
        $tempreplyto = array();

        // Make sure that we fall back onto some reasonable no-reply address.
        $noreplyaddressdefault = 'noreply@' . get_host_from_url($CFG->wwwroot);
        $noreplyaddress = empty($CFG->noreplyaddress) ? $noreplyaddressdefault : $CFG->noreplyaddress;

        if (!validate_email($noreplyaddress)) {
            debugging('email_to_user: Invalid noreply-email '.s($noreplyaddress));
            $noreplyaddress = $noreplyaddressdefault;
        }

        // Make up an email address for handling bounces.
        if (!empty($CFG->handlebounces)) {
            $modargs = 'B'.base64_encode(pack('V', $user->id)).substr(md5($user->email), 0, 16);
            $mail->Sender = generate_email_processing_address(0, $modargs);
        } else {
            $mail->Sender = $noreplyaddress;
        }

        // Make sure that the explicit replyto is valid, fall back to the implicit one.
        if (!empty($replyto) && !validate_email($replyto)) {
            debugging('email_to_user: Invalid replyto-email '.s($replyto));
            $replyto = $noreplyaddress;
        }

        if (is_string($from)) { // So we can pass whatever we want if there is need.
            $mail->From     = $noreplyaddress;
            $mail->FromName = $from;
        // Check if using the true address is true, and the email is in the list of allowed domains for sending email,
        // and that the senders email setting is either displayed to everyone, or display to only other users that are enrolled
        // in a course with the sender.
        } else if ($usetrueaddress && can_send_from_real_email_address($from, $user)) {
            if (!validate_email($from->email)) {
                debugging('email_to_user: Invalid from-email '.s($from->email).' - not sending');
                // Better not to use $noreplyaddress in this case.
                return false;
            }
            $mail->From = $from->email;
            $fromdetails = new stdClass();
            $fromdetails->name = fullname($from);
            $fromdetails->url = preg_replace('#^https?://#', '', $CFG->wwwroot);
            $fromdetails->siteshortname = format_string($SITE->shortname);
            $fromstring = $fromdetails->name;
            if ($CFG->emailfromvia == EMAIL_VIA_ALWAYS) {
                $fromstring = get_string('emailvia', 'core', $fromdetails);
            }
            $mail->FromName = $fromstring;
            if (empty($replyto)) {
                $tempreplyto[] = array($from->email, fullname($from));
            }
        } else {
            $mail->From = $noreplyaddress;
            $fromdetails = new stdClass();
            $fromdetails->name = fullname($from);
            $fromdetails->url = preg_replace('#^https?://#', '', $CFG->wwwroot);
            $fromdetails->siteshortname = format_string($SITE->shortname);
            $fromstring = $fromdetails->name;
            if ($CFG->emailfromvia != EMAIL_VIA_NEVER) {
                $fromstring = get_string('emailvia', 'core', $fromdetails);
            }
            $mail->FromName = $fromstring;
            if (empty($replyto)) {
                $tempreplyto[] = array($noreplyaddress, get_string('noreplyname'));
            }
        }

        if (!empty($replyto)) {
            $tempreplyto[] = array($replyto, $replytoname);
        }

        $temprecipients[] = array($user->email, fullname($user));

        // Set word wrap.
        $mail->WordWrap = $wordwrapwidth;

        if (!empty($from->customheaders)) {
            // Add custom headers.
            if (is_array($from->customheaders)) {
                foreach ($from->customheaders as $customheader) {
                    $mail->addCustomHeader($customheader);
                }
            } else {
                $mail->addCustomHeader($from->customheaders);
            }
        }

        if (!empty($from->bccaddress)) {
            // Add BCC.
            if (is_array($from->bccaddress)) {
                foreach ($from->bccaddress as $bccaddress) {
                    $mail->addBcc($bccaddress);
                }
            } else {
                $mail->addBcc($from->bccaddress);
            }
        }

        // If the X-PHP-Originating-Script email header is on then also add an additional
        // header with details of where exactly in moodle the email was triggered from,
        // either a call to message_send() or to email_to_user().
        if (ini_get('mail.add_x_header')) {

            $stack = debug_backtrace(false);
            $origin = $stack[0];

            foreach ($stack as $depth => $call) {
                if ($call['function'] == 'message_send') {
                    $origin = $call;
                }
            }

            $originheader = $CFG->wwwroot . ' => ' . gethostname() . ':'
                 . str_replace($CFG->dirroot . '/', '', $origin['file']) . ':' . $origin['line'];
            $mail->addCustomHeader('X-Moodle-Originating-Script: ' . $originheader);
        }

        if (!empty($CFG->emailheaders)) {
            $headers = array_map('trim', explode("\n", $CFG->emailheaders));
            foreach ($headers as $header) {
                if (!empty($header)) {
                    $mail->addCustomHeader($header);
                }
            }
        }

        if (!empty($from->priority)) {
            $mail->Priority = $from->priority;
        }

        $renderer = $PAGE->get_renderer('core');
        $context = array(
            'sitefullname' => $SITE->fullname,
            'siteshortname' => $SITE->shortname,
            'sitewwwroot' => $CFG->wwwroot,
            'subject' => $subject,
            'prefix' => $CFG->emailsubjectprefix,
            'to' => $user->email,
            'toname' => fullname($user),
            'from' => $mail->From,
            'fromname' => $mail->FromName,
        );
        if (!empty($tempreplyto[0])) {
            $context['replyto'] = $tempreplyto[0][0];
            $context['replytoname'] = $tempreplyto[0][1];
        }
        if ($user->id > 0) {
            $context['touserid'] = $user->id;
            $context['tousername'] = $user->username;
        }

        if (!empty($user->mailformat) && $user->mailformat == 1) {
            // Only process html templates if the user preferences allow html email.

            if (!$messagehtml) {
                // If no html has been given, BUT there is an html wrapping template then
                // auto convert the text to html and then wrap it.
                $messagehtml = trim(text_to_html($messagetext));
            }
            $context['body'] = $messagehtml;
            $messagehtml = $renderer->render_from_template('core/email_html', $context);
        }

        $context['body'] = html_to_text(nl2br($messagetext));
        $mail->Subject = $renderer->render_from_template('core/email_subject', $context);
        $mail->FromName = $renderer->render_from_template('core/email_fromname', $context);
        $messagetext = $renderer->render_from_template('core/email_text', $context);

        // Autogenerate a MessageID if it's missing.
        if (empty($mail->MessageID)) {
            $mail->MessageID = generate_email_messageid();
        }

        if ($messagehtml && !empty($user->mailformat) && $user->mailformat == 1) {
            // Don't ever send HTML to users who don't want it.
            $mail->isHTML(true);
            $mail->Encoding = 'quoted-printable';
            $mail->Body    =  $messagehtml;
            $mail->AltBody =  "\n$messagetext\n";
        } else {
            $mail->IsHTML(false);
            $mail->Body =  "\n$messagetext\n";
        }

        if ($attachments) {
            foreach ($attachments as $filename => $attachment) {
                $mimetype = mimeinfo('type', $filename);
                $mail->addAttachment($attachment, $filename, 'base64', $mimetype);
            }
        }

        // Check if the email should be sent in an other charset then the default UTF-8.
        if ((!empty($CFG->sitemailcharset) || !empty($CFG->allowusermailcharset))) {

            // Use the defined site mail charset or eventually the one preferred by the recipient.
            $charset = $CFG->sitemailcharset;
            if (!empty($CFG->allowusermailcharset)) {
                if ($useremailcharset = get_user_preferences('mailcharset', '0', $user->id)) {
                    $charset = $useremailcharset;
                }
            }

            // Convert all the necessary strings if the charset is supported.
            $charsets = get_list_of_charsets();
            unset($charsets['UTF-8']);
            if (in_array($charset, $charsets)) {
                $mail->CharSet  = $charset;
                $mail->FromName = core_text::convert($mail->FromName, 'utf-8', strtolower($charset));
                $mail->Subject  = core_text::convert($mail->Subject, 'utf-8', strtolower($charset));
                $mail->Body     = core_text::convert($mail->Body, 'utf-8', strtolower($charset));
                $mail->AltBody  = core_text::convert($mail->AltBody, 'utf-8', strtolower($charset));

                foreach ($temprecipients as $key => $values) {
                    $temprecipients[$key][1] = core_text::convert($values[1], 'utf-8', strtolower($charset));
                }
                foreach ($tempreplyto as $key => $values) {
                    $tempreplyto[$key][1] = core_text::convert($values[1], 'utf-8', strtolower($charset));
                }
            }
        }

        foreach ($temprecipients as $values) {
            $mail->addAddress($values[0], $values[1]);
        }
        foreach ($tempreplyto as $values) {
            $mail->addReplyTo($values[0], $values[1]);
        }

        if (!empty($CFG->emaildkimselector)) {
            $domain = substr(strrchr($mail->From, "@"), 1);
            $pempath = "{$CFG->dataroot}/dkim/{$domain}/{$CFG->emaildkimselector}.private";
            if (file_exists($pempath)) {
                $mail->DKIM_domain      = $domain;
                $mail->DKIM_private     = $pempath;
                $mail->DKIM_selector    = $CFG->emaildkimselector;
                $mail->DKIM_identity    = $mail->From;
            } else {
                debugging("Email DKIM selector chosen due to {$mail->From} but no certificate found at $pempath", DEBUG_DEVELOPER);
            }
        }

        if ($mail->send()) {
            set_send_count($user);
            if (!empty($mail->SMTPDebug)) {
                echo '</pre>';
            }
            return true;
        } else {
            // Trigger event for failing to send email.
            $event = \core\event\email_failed::create(array(
                'context' => context_system::instance(),
                'userid' => $from->id,
                'relateduserid' => $user->id,
                'other' => array(
                    'subject' => $subject,
                    'message' => $messagetext,
                    'errorinfo' => $mail->ErrorInfo
                )
            ));
            $event->trigger();
            if (CLI_SCRIPT) {
                mtrace('Error: lib/moodlelib.php email_to_user(): '.$mail->ErrorInfo);
            }
            if (!empty($mail->SMTPDebug)) {
                echo '</pre>';
            }
            return false;
        }
    }


}