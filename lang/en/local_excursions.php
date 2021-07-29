<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin strings are defined here.
 *
 * @package     local_excursions
 * @category    string
 * @copyright   2021 Michael Vangelovski
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Activity planning';
$string['pluginname_desc'] = 'An activity planning system for CGS.';

$string['settingsheaderdb'] = 'Synergetic database connection';
$string['dbtype'] = 'Database driver';
$string['dbtype_desc'] = 'ADOdb database driver name, type of the external database engine.';
$string['dbhost'] = 'Database host';
$string['dbhost_desc'] = 'Type database server IP address or host name. Use a system DSN name if using ODBC. Use a PDO DSN if using PDO.';
$string['dbname'] = 'Database name';
$string['dbuser'] = 'Database user';
$string['dbpass'] = 'Database password';
$string['usertaglistssql'] = 'User taglists SQL';
$string['publictaglistssql'] = 'Public taglists SQL';
$string['taglistuserssql'] = 'Taglist users SQL';
$string['checkabsencesql'] = 'Check absence SQL';
$string['createabsencesql'] = 'Create absence SQL';
$string['deleteabsencessql'] = 'Delete absences SQL';
$string['studentdatachecksql'] = 'Student Data Check SQL';
$string['excursionconsentsql'] = 'Excursions consent SQL';

$string['excursions:audit'] = 'Audit local_excursions activities';

$string['activitiesetup'] = 'Activity setup';
$string['activities'] = 'Activities';
$string['privacy:metadata'] = 'local_excursions does not store any personal data.';
$string['excursions:manage'] = 'Manage excursions';

$string['activityform:details'] = 'Activity details';
$string['activityform:create'] = 'Create a new activity';
$string['activityform:editsuccess'] = 'Activity was successfully edited.';
$string['activityform:savechangessuccess'] = 'Changes were successfully saved.';
$string['activityform:publishsuccess'] = 'Activity was successfully published.';
$string['activityform:sentforreviewsuccess'] = 'The activity was sent for review.';
$string['activityform:savefail'] = 'Failed to save changes';
$string['activityform:name'] = 'Activity name';
$string['activityform:campus'] = 'Campus';
$string['activityform:campus_desc'] = 'Senior should be chosen for whole school events.';
$string['activityform:activitytype'] = 'Type';
$string['activityform:cohort'] = 'Cohort';
$string['activityform:location'] = 'Location';
$string['activityform:notes'] = 'Details <br><small>Summary of event</small>';
$string['activityform:transport'] = 'Transport <br><small>E.g. walking, bus, taxi, including authorised driver</small>';
$string['activityform:timestart'] = 'Start time';
$string['activityform:timeend'] = 'End time';
$string['helpguide'] = '<a target="_blank" href="https://kb.cgs.act.edu.au/guides/excursion-planning/">KB Guide</a>';

$string['activityform:staffincharge'] = 'Staff member in charge';
$string['activityform:accompanyingstaff'] = 'Accompanying staff';
$string['activityform:otherparticipants'] = 'Non-school participants';
$string['activityform:staff'] = 'Staff';
$string['activityform:paperwork'] = 'Paperwork';
$string['activityform:riskassessment'] = 'Risk assessment<br><small><a target="_blank" href="https://kb.cgs.act.edu.au/guides/risk-assessment-template/">Template</a></small>';
$string['activityform:chargesheet'] = 'Chargesheet';
$string['activityform:chargesheet_desc'] = 'This is a chargesheet template automatically generated using the student list above. You may download the <strong><a class="chargesheet-link" target="_blank" href="/local/excursions/generate.php?doc=chargesheet&activityid={$a}">here</a></strong>.';
$string['activityform:medicalreport'] = 'Medical report';
$string['activityform:medicalreportdesc'] = 'The medical report is automatically generated using the student list above. It can be accessed at any time and you do not have to upload it. You may view the report <strong><a class="medical-report-link" target="_blank" href="localised_url_here&activityid={$a}">here</a></strong>.';
$string['activityform:attachments'] = 'Additional attachments';
$string['activityform:students'] = 'Student list';
$string['activityform:addstudents'] = 'Add students';
$string['activityform:addmorestudents'] = 'Add more students';
$string['activityform:removeselected'] = 'Remove selected';
$string['activityform:preparemessage'] = 'Prepare message';
$string['activityform:permissions'] = 'Permissions';
$string['activityform:permissionsdesc'] = 'Permissions/invitations are needed for this activity.';
$string['activityform:recipientplaceholder'] = 'Search by name';
$string['activityform:medicalreportlink'] = 'Medical report';
$string['activityform:savemayinvalidate'] = 'Note, major changes (such as new activity dates) will invalidate existing approvals and resubmit the activity for approval.';
$string['activityform:sendforreview'] = 'Send for review';
$string['activityform:cancel'] = 'Cancel';
$string['activityform:savechanges'] = 'Save changes';
$string['activityform:savedraft'] = 'Save draft';
$string['activityform:delete'] = 'Delete';
$string['activityform:checkall'] = 'Select all';
$string['activityform:uncheckall'] = 'Deselect all';
$string['activityform:cost'] = 'Cost for student';

$string['enablepermissions'] = 'This activity requires parent permission.';
$string['enablepermissionshelplink'] = 'Do I need parent permission?';
$string['enablepermissionshelpbody'] = '<p>Do not request parent permission unnecessarily. Bulk excursion permissions are obtained from parents annually.</p><p>You only need to request parent permission for your excursion if it is:</p><ul><li>international</li><li>overnight</li><li>CGS Care, PreK or Pre S or</li><li>additional risks (such as swimming) have been identified</li><ul>';

$string['invitetypesystem'] = 'To send a system-generated email to parents, select the students below, and click the <b><i class="fa fa-envelope" aria-hidden="true"></i> Prepare message</b> button.';
$string['invitetypemanual'] = 'To communiticate with parents manually or via an alternate system, direct parents to the following URL to register their permission.';
$string['view:noactivities'] = 'No relevant activities found.';
$string['status:autosave'] = 'Autosave draft';
$string['status:draft'] = 'Draft';
$string['status:inreview'] = 'In review';
$string['status:waiting'] = 'Waiting';
$string['status:approved'] = 'Approved';
$string['status:cancelled'] = 'Cancelled';
$string['status:waitingforyou'] = 'Waiting for you';
$string['pastevent'] = 'Past event';
$string['pastdueby'] = 'Permissions can no longer be given for this activity because it is past the due by date.';
$string['pastlimit'] = 'The maximum number of students has been reached for this activity.';

$string['cron_queue_permissions'] = 'Queue permissions for sending';
$string['cron_send_permissions'] = 'Send permission notifications';
$string['cron_create_absences'] = 'Create excursion absences in Synergetic';
$string['messagequeued'] = 'Permissions have been queued for sending. Permissions will appear in the student list above as parents respond.';

$string['activitypermissions'] = 'Activity Permissions';
$string['savechangesrequired'] = 'The student list has changed. <strong>Save changes</strong> is required to refresh permissions.';
$string['extrapermissionsinfo'] = 'If your child has recently had a medical condition or a change to medications that the School may not yet be aware of, please update your child\'s information via the <u><b><a href="https://infiniti.canberragrammar.org.au/Infiniti/Produce/launch.aspx?id=f95c8a98-8410-4a3e-ab46-0c907ddb9390&portal=1" target="_blank" rel="noopener">Student Data Form</a></b></u>';
$string['studentnotuptodate'] = '<small>Your student may not be able to attend until updated information has been provided through the <b><u><a href="https://infiniti.canberragrammar.org.au/Infiniti/Produce/launch.aspx?id=f95c8a98-8410-4a3e-ab46-0c907ddb9390&portal=1" target="_blank" rel="noopener">Student Data Form</a></u></b>. We ask for this to be updated at least once each year.</small>';
$string['studentinlistnotuptodate'] = 'Student information is not up to date. Parents should be encouraged to complete the student data form. If permissions are enabled parents will be advised by the system.';
$string['nosisconsent'] = 'Parents do not consent to excursion participation.';
