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
 * Display Announcements.
 *
 * @package   local_announcements
 * @copyright 2020 Michael Vangelovski <michael.vangelovski@hotmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

// Include required files and classes.
require_once(dirname(__FILE__) . '/../../config.php');
require_once('lib.php');
use \local_excursions\libs\graphlib;

// Set context.
$context = context_system::instance();

// Set up page parameters.
$PAGE->set_context($context);
$pageurl = new moodle_url('/local/excursions/debugger.php');
$PAGE->set_url($pageurl);
$title = get_string('pluginname', 'local_excursions');
$PAGE->set_heading($title);
$PAGE->set_title($SITE->fullname . ': ' . $title);
$PAGE->navbar->add($title);
// Check user is logged in.
require_login();
require_capability('moodle/site:config', $context, $USER->id); 

echo "<pre>"; 


$cron = new \local_excursions\task\cron_sync_planning();
$cron->execute();
exit;





$userPrincipalName = 'michael.vangelovski@cgs.act.edu.au';

// Create calendar event
$eventdata = new stdClass();
$eventdata->subject = "Let's go for lunch 2";
$eventdata->body = new stdClass();
$eventdata->body->contentType = "HTML";
$eventdata->body->content = "<b>Does</b> next month work for you?";
$eventdata->categories = array('Blue', 'Test Category');
$eventdata->start = new stdClass();
$eventdata->start->dateTime = "2023-07-24T15:11:00";
$eventdata->start->timeZone = "AUS Eastern Standard Time";
$eventdata->end = new stdClass();
$eventdata->end->dateTime = "2023-07-24T16:12:00";
$eventdata->end->timeZone = "AUS Eastern Standard Time";
$eventdata->location = new stdClass();
$eventdata->location->displayName = "Data centre";
$eventdata->isOnlineMeeting = false;

try {
    $result = graphlib::createEvent($userPrincipalName, $eventdata);
} catch (\Exception $e) {
    var_export('error');
    exit;
}


var_export($result);
$record = new stdClass();
$record->calendar = $userPrincipalName;
$record->extenalid = $result->getId();
$record->changekey = $result->getChangeKey();
$record->weblink = $result->getWebLink();
$record->status = 1;
$record->timesynclive = time();
var_export($record);
echo "<hr>";


// Get calendar event
$result = graphlib::getEvent($userPrincipalName, $record->extenalid);
var_export($result);
echo "<hr>";

// Update calendar event
$eventdata = new stdClass();
$eventdata->subject = "A new subject name";
$eventdata->body = new stdClass();
$eventdata->body->contentType = "HTML";
$eventdata->body->content = "<b>Change this</b> next month work for you?";
$eventdata->categories = array('Red Category');
$eventdata->start = new stdClass();
$eventdata->start->dateTime = "2023-07-24T15:10:00";
$eventdata->start->timeZone = "AUS Eastern Standard Time";
$eventdata->end = new stdClass();
$eventdata->end->dateTime = "2023-07-24T16:10:00";
$eventdata->end->timeZone = "AUS Eastern Standard Time";
$eventdata->location = new stdClass();
$eventdata->location->displayName = "Admin Building";
$result = graphlib::updateEvent($userPrincipalName, $record->extenalid, $eventdata);
var_export($result);
echo "<hr>";

// Delete calendar event
$result = graphlib::deleteEvent($userPrincipalName, $record->extenalid);
var_export($result->getStatus() == 204);
echo "<hr>";

exit;