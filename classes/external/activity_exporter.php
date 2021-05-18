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
 * Provides {@link local_excursions\external\activity_exporter} class.
 *
 * @package   local_excursions
 * @copyright 2021 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_excursions\external;

defined('MOODLE_INTERNAL') || die();

use core\external\persistent_exporter;
use renderer_base;
use local_excursions\persistents\activity;
use local_excursions\locallib;

/**
 * Exporter of a single activity
 */
class activity_exporter extends persistent_exporter {

    /**
    * Returns the specific class the persistent should be an instance of.
    *
    * @return string
    */
    protected static function define_class() {
        return activity::class; 
    }

    /**
    * Return the list of additional properties.
    *
    * Calculated values or properties generated on the fly based on standard properties and related data.
    *
    * @return array
    */
    protected static function define_other_properties() {
        return [
            'manageurl' => [
                'type' => PARAM_RAW,
            ],
            'permissionsurl' => [
                'type' => PARAM_RAW,
            ],
            'summaryurl' => [
                'type' => PARAM_RAW,
            ],
            'startreadabletime' => [
                'type' => PARAM_RAW,
            ],
            'endreadabletime' => [
                'type' => PARAM_RAW,
            ],
            'createdreadabletime' => [
                'type' => PARAM_RAW,
            ],
            'statushelper' => [
                'type' => PARAM_RAW,
            ],
            'iscreator' => [
                'type' => PARAM_BOOL,
            ],
            'isapprover' => [
                'type' => PARAM_BOOL,
            ],
            'isstaffincharge' => [
                'type' => PARAM_BOOL,
            ],
            'iswaitingforyou' => [
                'type' => PARAM_BOOL,
            ],
            'approvals' => [
                'type' => PARAM_RAW,
            ],
            'permissionshelper' => [
                'type' => PARAM_RAW,
            ],
            'messagehistory' => [
                'type' => PARAM_RAW,
            ],
            'userpermissions' => [
                'type' => PARAM_RAW,
            ],
            'userhasstudents' => [
                'type' => PARAM_INT,
            ],
            'staffinchargeinfo' => [
                'type' => PARAM_RAW,
            ],
            'usercanedit' => [
                'type' => PARAM_BOOL,
            ],
            'formattedra' => [
                'type' => PARAM_RAW,
            ],
            'formattedattachments' => [
                'type' => PARAM_RAW,
            ],
            'calicons' => [
                'type' => PARAM_RAW,
            ],
            'ispast' => [
                'type' => PARAM_BOOL,
            ],
            'numstudents' => [
                'type' => PARAM_INT,
            ],
            'htmlnotes' => [
                'type' => PARAM_RAW,
            ],
        ];
    }

    /**
     * Get the additional values to inject while exporting.
     *
     * @param renderer_base $output The renderer.
     * @return array Keys are the property names, values are their values.
     */
    protected function get_other_values(renderer_base $output) {
        global $PAGE, $USER, $DB;

        $manageurl = new \moodle_url('/local/excursions/activity.php', array(
            'edit' => $this->data->id,
        ));

        $permissionsurl = new \moodle_url('/local/excursions/permissions.php', array(
            'activityid' => $this->data->id,
        ));

        $summaryurl = new \moodle_url('/local/excursions/summary.php', array(
            'id' => $this->data->id,
        ));

        $startreadabletime = '';
        if ($this->data->timestart > 0) {
            $startreadabletime = date('j M Y, g:ia', $this->data->timestart);
        }
        $startthisyear = date('Y', $this->data->timestart) == date('Y', time());
        $endthisyear = date('Y', $this->data->timeend) == date('Y', time());
        $calicons = array(
            'start' => $output->render_from_template('local_excursions/cal_date', array(
                'day' => date('j', $this->data->timestart),
                'month' => date('M', $this->data->timestart),
                'year' => $startthisyear ? '' : date('Y', $this->data->timestart),
                'time' => date('g:ia', $this->data->timestart),
            )),
            'end' => $output->render_from_template('local_excursions/cal_date', array(
                'day' => date('j', $this->data->timeend),
                'month' => date('M', $this->data->timeend),
                'year' => $endthisyear ? '' : date('Y', $this->data->timestart),
                'time' => date('g:ia', $this->data->timeend),
                'issameday' => (date('j M Y', $this->data->timestart) == date('j M Y', $this->data->timeend)),
            )),
        );

        $ispast = false;
        if ($this->data->timeend < time()) {
            $ispast = true;
        }

        $endreadabletime = '';
        if ($this->data->timeend > 0) {
            $endreadabletime = date('j M Y, g:ia', $this->data->timeend);
        }

        $createdreadabletime = '';
        if ($this->data->timecreated > 0) {
            $createdreadabletime = date('j M Y, g:ia', $this->data->timecreated);
        }

        $statushelper = locallib::status_helper($this->data->status);

        $iscreator = ($this->data->username == $USER->username);

        $isapprover = activity::is_approver_of_activity($this->data->id);
        if ($isapprover) {
            $userapprovertypes = locallib::get_approver_types($USER->username);
        }

        // Get approvals.
        $iswaitingforyou = false;
        $approvals = activity::get_approvals($this->data->id);
        $i = 0;
        foreach ($approvals as $approval) {
            if ($approval->username) {
                $user = \core_user::get_user_by_username($approval->username);
                if ($user) {
                    $userphoto = new \user_picture($user);
                    $userphoto->size = 2; // Size f2.
                    $approval->userphoto = $userphoto->get_url($PAGE)->out(false); 
                } else {
                    $approval->userphoto = new \moodle_url('/user/pix.php/0/f2.jpg');
                }
                
            }
            if(++$i === count($approvals)) {
                $approval->last = true;
            }
            if ($isapprover) {
                if (in_array($approval->type, $userapprovertypes)) {
                    // No unactioned prerequisites found, user can approver this.
                    $prerequisites = activity::get_prerequisites($this->data->id, $approval->type);
                    if (empty($prerequisites)) {
                        $approval->canapprove = true;
                        if ($approval->status == 0) { //
                            $iswaitingforyou = true;
                        }
                    }
                    
                }
            }
        }

        $permissionshelper = locallib::permissions_helper($this->data->id);

        $messagehistory = array_values(activity::get_messagehistory($this->data->id));
        foreach ($messagehistory as &$emailaction) {
            $emailaction->readabletime = date('j M Y g:ia', $emailaction->timecreated);
            if ($emailaction->status == 0) {
                $emailaction->statusreadable = 'Queued';
            }
            if ($emailaction->status == 1) {
                $emailaction->statusreadable = 'Sending';
            }
            if ($emailaction->status == 2) {
                $emailaction->statusreadable = 'Sent';
            }
            $emailaction->students = array();
            $students = json_decode($emailaction->studentsjson);
            foreach ($students as $username) {
                $emailaction->students[] = fullname(\core_user::get_user_by_username($username));
            }
            $emailaction->sender = fullname(\core_user::get_user_by_username($emailaction->username));
        }

        $userpermissions = array_values(activity::get_parent_permissions($this->data->id, $USER->username));
        foreach ($userpermissions as &$permission) {
            $student = \core_user::get_user_by_username($permission->studentusername);
            $permission->fullname = fullname($student);
            $userphoto = new \user_picture($student);
            $userphoto->size = 2; // Size f2.
            $permission->userphoto = $userphoto->get_url($PAGE)->out(false);
            $permission->isyes = ($permission->response == 1);
            $permission->isno = ($permission->response == 2);
            $permission->uptodate = locallib::get_studentdatacheck($permission->studentusername);
        }

        $staffinchargeinfo = new \stdClass();
        $sic = \core_user::get_user_by_username($this->data->staffincharge);
        if (empty($sic)) {
            $sic = \core_user::get_user_by_username($this->data->username);
        }
        $staffinchargeinfo = new \stdClass();
        if ($sic) {
            $staffinchargeinfo->fullname = fullname($sic);
            $sicphoto = new \user_picture($sic);
            $sicphoto->size = 2; // Size f2.
            $staffinchargeinfo->userphoto = $sicphoto->get_url($PAGE)->out(false);
        }
        $isstaffincharge = false;
        if ($sic->username == $USER->username) {
            $isstaffincharge = true;
        }

        $usercanedit = false;
        if ($iscreator || $isstaffincharge || $isapprover) {
            $usercanedit = true;
        }

        $formattedra = $this->export_riskassessment($output);

        $formattedattachments = $this->export_attachments($output);

        $numstudents = count(activity::get_excursion_students($this->data->id));

        $htmlnotes = nl2br($this->data->notes);

    	return [
            'manageurl' => $manageurl->out(false),
            'permissionsurl' => $permissionsurl->out(false),
            'summaryurl' => $summaryurl->out(false),
            'createdreadabletime' => $createdreadabletime,
            'startreadabletime' => $startreadabletime,
            'endreadabletime' => $endreadabletime,
            'statushelper' => $statushelper,
            'iscreator' => $iscreator,
            'isapprover' => $isapprover,
            'isstaffincharge' => $isstaffincharge,
            'iswaitingforyou' => $iswaitingforyou,
            'approvals' => $approvals,
            'permissionshelper' => $permissionshelper,
            'messagehistory' => $messagehistory,
            'userpermissions' => $userpermissions,
            'userhasstudents' => count($userpermissions),
            'staffinchargeinfo' => $staffinchargeinfo,
            'usercanedit' => $usercanedit,
            'formattedra' => $formattedra,
            'formattedattachments' => $formattedattachments,
            'calicons' => $calicons,
            'ispast' => $ispast,
            'numstudents' => $numstudents,
            'htmlnotes' => $htmlnotes,
	    ];
    }


    private function export_attachments(renderer_base $output) {
        global $CFG;

        $context = \context_system::instance();

        $attachments = [];
        // We retrieve all files according to the time that they were created.  In the case that several files were uploaded
        // at the sametime (e.g. in the case of drag/drop upload) we revert to using the filename.
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'local_excursions', 'attachments', $this->data->id, "filename", false);
        if ($files) {
            foreach ($files as $file) {
                $filename = $file->get_filename();
                $mimetype = $file->get_mimetype();
                $iconimage = $output->pix_icon(file_file_icon($file), get_mimetype_description($file), 'moodle', array('class' => 'icon'));
                $path = file_encode_url($CFG->wwwroot.'/pluginfile.php', '/'.$context->id.'/local_excursions/attachments/'.$this->data->id.'/'.$filename);

                $isimage = in_array($mimetype, array('image/gif', 'image/jpeg', 'image/png')) ? 1 : 0;

                $attachment = [
                    'filename' => $filename,
                    'formattedfilename' => format_text($filename, FORMAT_HTML, array('context'=>$context)),
                    'mimetype' => $mimetype,
                    'iconimage' => $iconimage,
                    'path' => $path,
                    'isimage' => $isimage,
                ];
                $attachments[] = $attachment;
            }
        }

        return $attachments;
    }


    private function export_riskassessment(renderer_base $output) {
        global $CFG;

        $context = \context_system::instance();

        $attachments = [];
        // We retrieve all files according to the time that they were created.  In the case that several files were uploaded
        // at the sametime (e.g. in the case of drag/drop upload) we revert to using the filename.
        $fs = get_file_storage();

        $files = $fs->get_area_files($context->id, 'local_excursions', 'ra', $this->data->id, "filename", false);
        if ($files) {
            foreach ($files as $file) {
                $filename = $file->get_filename();
                $mimetype = $file->get_mimetype();
                $iconimage = $output->pix_icon(file_file_icon($file), get_mimetype_description($file), 'moodle', array('class' => 'icon'));
                $path = file_encode_url($CFG->wwwroot.'/pluginfile.php', '/'.$context->id.'/local_excursions/ra/'.$this->data->id.'/'.$filename);

                $isimage = in_array($mimetype, array('image/gif', 'image/jpeg', 'image/png')) ? 1 : 0;

                $attachment = [
                    'filename' => $filename,
                    'formattedfilename' => format_text($filename, FORMAT_HTML, array('context'=>$context)),
                    'mimetype' => $mimetype,
                    'iconimage' => $iconimage,
                    'path' => $path,
                    'isimage' => $isimage,
                ];
                $attachments[] = $attachment;
            }
        }

        return $attachments;
    }

}
