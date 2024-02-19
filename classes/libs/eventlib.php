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
 * @copyright 2023 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_excursions\libs;

defined('MOODLE_INTERNAL') || die();

use \local_excursions\persistents\activity;
use \local_excursions\locallib;
use \local_excursions\external\activity_exporter;

class eventlib {

    public static function get_event($id) {
        global $DB;

        if (empty($id)) {
            return new \stdClass();
        }

        $event = $DB->get_record('excursions_events', array('id' => $id, 'deleted' => 0));

        if (empty($event)) {
            return false;
        }

        return $event;
    }

    public static function get_by_activityid($activityid) {
        global $DB;

        $event = $DB->get_record('excursions_events', array('deleted' => 0, 'isactivity' => 1, 'activityid' => $activityid));

        if (empty($event)) {
            return false;
        }

        return $event;
    }

    public static function save_event($formdata) {
        global $DB, $USER;

        //echo "<pre>"; var_export($formdata); exit;

        $event = eventlib::get_event($formdata->edit);
        if ($formdata->edit && $event === false) {
            // Editing but no event found. Major error.
            return;
        }
        $original = clone($event);

        $event->activityname = $formdata->activityname;
        $event->activitytype = $formdata->activitytype;
        $event->location = $formdata->location;
        $event->timestart = $formdata->timestart;
        $event->timeend = $formdata->timeend;
        $event->nonnegotiable = $formdata->nonnegotiable;
        $event->reason = isset($formdata->nonnegotiablereason) ? $formdata->nonnegotiablereason : '';
        $event->notes = $formdata->notes;
        $event->categoriesjson = $formdata->categoriesjson;
        $event->ownerjson = $formdata->ownerjson;
        $event->owner = $USER->username;
        $event->assessment = $formdata->assessment;
        $event->courseid = isset($formdata->courseselect) ? $formdata->courseselect : 0;
        $event->assessmenturl = isset($formdata->assessmenturl) ? $formdata->assessmenturl : '';
        $event->colourcategory = isset($formdata->colourselect) ? $formdata->colourselect : '';
        $event->displaypublic = $formdata->displaypublic;
        $event->timemodified = time();
        $owner = json_decode($formdata->ownerjson);
        if ($owner) {
            $owner = array_pop($owner);
            $event->owner = $owner->idfield;
        }
        $areas = json_decode($formdata->categoriesjson);
        $areas = array_map(function($cat) {
            return explode('/', $cat);
        }, $areas);
        $areas = call_user_func_array('array_merge', $areas);
        $areas = array_values(array_unique($areas));
        $event->areasjson = json_encode($areas);
        if (!count($areas) || in_array('CGS Board', $areas)) {
            $event->displaypublic = 0;
        }

        $campuses = [];
        if (array_intersect($areas, ['Whole school', 'Primary school', 'Pre-School', 'Pre-Kindergarten', 'Kindergarten', 'Year 1', 'Year 2', 'Year 3', 'Year 4', 'Year 5', 'Year 6'])) {
            $campuses[] = 'primary';
        }
        if (array_intersect($areas, ['Whole school', 'Senior school', 'Academic', 'House', 'Year 7', 'Year 8', 'Year 9', 'Year 10', 'Year 11', 'Year 12'])) {
            $campuses[] = 'senior';
        }
        $event->campus = implode(',', $campuses);

        // Disabled Recurring
        /*************************
        // If editing an existing recurring event, things get weird. Ignore and clear recurring settings if they've opted to edit a single occurance.
        if (!empty($event->id) && isset($formdata->editseries) && $formdata->editseries == 'event' ) {
            $formdata->recurring = 0;
        }
        // If editing an existing recurring event, opts to edit entire series, and user unticks the recurring setting, then all occurances are deleted and this is converted into a single event.
        if (!empty($event->id) && $event->recurrencemaster && isset($formdata->editseries) && $formdata->editseries == 'series' && !$formdata->recurring) {
            $DB->execute("DELETE FROM {excursions_events} WHERE recurrencemaster = ?", [$event->recurrencemaster]);
            unset($event->id);
        }

        // Expland dates if this is a recurring event.
        $dates = false;
        if ($formdata->recurring) {
            $recurringdata = (object) [
                'recurring' => $formdata->recurring,
                'recurringpattern' => $formdata->recurringpattern,
                'recurringdailypattern' => $formdata->recurringdailypattern,
                'recuruntil' => $formdata->recuruntil,
            ];
            $event->recurringjson = json_encode($recurringdata);
            $dates = (object) static::expand_dates($recurringdata, $event->timestart, $event->timeend);
        }

        // If there are multuple dates (recurring) create a series.
        if ($dates !== false) {
            $dates = $dates->dates;
            if (empty($event->id)) {
                // New series of events.
                static::create_new_series_from_data($event, $dates);
            } else {
                if ($event->recurrencemaster == 0) {
                    // The event was not already part of a series. Conver this single event into a series.
                    // Delete single event.
                    $DB->execute("DELETE FROM {excursions_events} WHERE id = ?", [$event->id]);
                } else {
                    // The event was already part of a series. Need to recreate the entire series.
                    // Delete entire series.
                    $DB->execute("DELETE FROM {excursions_events} WHERE recurrencemaster = ?", [$event->recurrencemaster]);
                }
                static::create_new_series_from_data($event, $dates);
            }
            return;
        } 
        **************************/

        // Single event. If it was previously in a series it will be detached.
        // TODO: Remove rucurring columns and tables from db if not needed.
        $event->recurrencemaster = 0;
        $event->recurringjson = '';

        // Simple single event.         
        if (empty($event->id)) {
            $event->timecreated = time();
            $event->creator = $USER->username;
            $event->id = $DB->insert_record('excursions_events', $event);
        } else {
            // if dates have changed, we need to stop syncing event until it is reapproved..
            if ($event->timestart != $original->timestart || $event->timeend != $original->timeend) {
                $event->status = 0;
            }
            $DB->update_record('excursions_events', $event);
        }

        if ($formdata->assessment == 1) {
            $formdata->entrytype = 'event';
        }

        if ($formdata->entrytype == 'excursion') {  
            // Sync with activity data.
            if (!empty($event->activityid)) {
                // Make sure this is marked as activity. This is because it can be unmarked from a previous excursion-to-event conversion.
                $event->isactivity = 1;
                $DB->update_record('excursions_events', $event);

                $originalactivity = new activity($event->activityid);
                $activity = new activity($event->activityid);
                // Copy values from event.
                $activity->set('activityname', $formdata->activityname);
                $activity->set('timestart', $formdata->timestart);
                $activity->set('timeend', $formdata->timeend);
                $activity->set('activitytype', 'excursion');
                if ($formdata->activitytype == 'oncampus') {
                    $activity->set('activitytype', 'incursion');
                }
                $activity->set('location', $formdata->location);
                $activity->set('notes', $formdata->notes);
                $activity->set('deleted', 0);
                $activity->save();
                 // If sending for review or saving after already in review, regenerate approvals.
                if ($activity->get('status') == locallib::ACTIVITY_STATUS_INREVIEW ||
                    $activity->get('status') == locallib::ACTIVITY_STATUS_APPROVED) {
                    activity::generate_approvals($originalactivity, $activity);
                }
            } else {
                // Create a new activity.
                $data = new \stdClass();
                // Copy values from event.
                $data->activityname = $formdata->activityname;
                $data->timestart = $formdata->timestart;
                $data->timeend = $formdata->timeend;
                $data->activitytype = 'excursion';
                if ($formdata->activitytype == 'oncampus') {
                    $data->activitytype = 'incursion';
                }
                $data->location = $formdata->location;
                $data->notes = $formdata->notes;
                // Other defaults
                $data->username = $USER->username;
                $data->permissionstype = 'system';
                $data->status = locallib::ACTIVITY_STATUS_DRAFT;
                $data->cost = '0';
                // Set the staff in charge.
                $data->staffinchargejson = $formdata->ownerjson;
                $data->staffincharge = $USER->username;
                $staffincharge = json_decode($formdata->ownerjson);
                if ($staffincharge) {
                    $staffincharge = array_pop($staffincharge);
                    $data->staffincharge = $staffincharge->idfield;
                }
                // Create activity.
                $activity = new activity(0, $data);
                $activity->save();
                // Insert reference to excursion.
                $event->isactivity = 1;
                $event->activityid = $activity->get('id');
                $DB->update_record('excursions_events', $event);
            }
        } else {
            // Convert excursion into simple event.
            if (isset($event->isactivity) && $event->isactivity == 1) {
                $event->isactivity = 0;
                $DB->update_record('excursions_events', $event);
                // Soft delete the excursion.
                if (!empty($event->activityid)) {
                    $activity = new activity($event->activityid);
                    $activity->set('deleted', 1);
                    $activity->save();
                }
            }
        }
    
        return $event->id;
    }

    /*public static function create_new_series_from_data($event, $dates) {
        global $DB;

        // Create a series.
        $series = new \stdClass();
        $series->data = '';
        $series->id = $DB->insert_record('excursions_event_series', $series);

        // Make recurring events based on dates.
        $occurances = [];
        foreach($dates as $date) {
            $occurance = clone $event;
            $occurance->timestart = $date['start'];
            $occurance->timeend = $date['end'];
            $occurance->recurrencemaster = $series->id;
            $occurance->id = $DB->insert_record('excursions_events', $occurance);
            $occurances[] = $occurance;
        }
        // Add helpful data to the series.
        $series->data = json_encode((object) [
            'pattern' => $event->recurringjson,
            'occuranceids' => array_column($occurances, 'id'),
        ]);
        $DB->update_record('excursions_event_series', $series);
    }

    public static function expand_dates($recurring, $timestart, $timeend) {
        $dates = array();
        $datesReadable = array();
        $format = "D, j M Y g:ia";
        
        if ($recurring->recurringpattern == 'daily') {
            // If daily is selected but event goes for longer than a day, that's an issue.
            $daystart = date('d', $timestart);
            $dayend = date('d', $timeend);
            if ($daystart !== $dayend) {
                return false;
            }
            while ($timestart <= $recurring->recuruntil) {
                if ($recurring->recurringdailypattern == 'weekdays') {
                    if (date('N', $timestart) < 6) {
                        $dates[] = array('start' => $timestart,'end' => $timeend);
                        $datesReadable[] = array('start' => date($format, $timestart),'end' => date($format, $timeend));
                    }
                } else {
                    $dates[] = array('start' => $timestart,'end' => $timeend);
                    $datesReadable[] = array('start' => date($format, $timestart),'end' => date($format, $timeend));
                }
                $timestart += 60*60*24;
                $timeend += 60*60*24;
            };
        } else if ($recurring->recurringpattern == 'weekly') {
            // If week is selected but event goes for longer than a week, that's an issue.
            if ($timeend-$timestart > 604800) { // seconds in a week.
                return false;
            }
            while ($timestart <= $recurring->recuruntil) {
                $dates[] = array('start' => $timestart,'end' => $timeend);
                $datesReadable[] = array('start' => date($format, $timestart),'end' => date($format, $timeend));
                $timestart += 60*60*24*7;
                $timeend += 60*60*24*7;
            };
        }

        //if (empty($dates)) {
        //    return false;
       // }

        return ['dates'=> $dates, 'datesReadable'=> $datesReadable];
    }*/

    public static function get_all_events_activities($current = '', $status = 0, $campus = 'ws', $user = '') {
        // If no month-year nav supplied, load for current month-year.
        if (empty($current)) {
            $current = date('Y-m', time());
        }
        $broken = explode('-', $current);
        $currentstart = strtotime($broken[0] . '-' . $broken[1] . '-1 00:00');
        if ($broken[1] == 12) {
            $currentend = strtotime($broken[0]+1 . '-1-1 00:00');
        } else {
            $currentend = strtotime($broken[0] . '-' . ($broken[1]+1) . '-1 00:00');
        }


        return static::get_for_date_range($currentstart, $currentend, $status, $campus, $user);
    }


    public static function get_for_date_range($currentstart, $currentend, $status = 0, $campus = 'ws', $user = '') {
        global $DB, $OUTPUT, $USER;

        // Sanitise status.
        $autosave = locallib::ACTIVITY_STATUS_AUTOSAVE;
        $draft = locallib::ACTIVITY_STATUS_DRAFT;
        $inreview = locallib::ACTIVITY_STATUS_INREVIEW;
        $approved = locallib::ACTIVITY_STATUS_APPROVED;
        if ($status < $draft || $status > $approved) {
            $status = 0;
        }

        // Formulate the user condition.
        $usersql = '';
        if ($user == 'self') {
            $usersql = " 
            AND (
                username = '$USER->username' OR
                staffincharge = '$USER->username' OR 
                planningstaffjson LIKE '%$USER->username,%' OR 
                accompanyingstaffjson LIKE '%$USER->username,%'
            )
            ";
        }

        // Formulate the campus condition.
        $campussql = '';
        if ($campus == 'ss') {
            $campussql = " AND campus = 'senior' ";
        } else if ($campus == 'ps') {
            $campussql = " AND campus = 'primary' ";
        }

        /* ----
        * Get activities. 
        * ----*/
        $sql = "";
        // If status not provided, then we want to get all approved activities + draft/inreview activities for this user only.
        // In the case of auditors/approvers, we need to get the above + all inreview activities.
        $auditor = has_capability('local/excursions:audit', \context_system::instance(), null, false);
        $approver = count(locallib::get_approver_types($USER->username)) > 0;
        if ($auditor || $approver) {
            if ($status == locallib::ACTIVITY_STATUS_DRAFT) {
                // Get this user's draft activities
                $sql = "SELECT *
                        FROM {excursions} 
                        WHERE ((timestart >= ? AND timestart < ?) OR (timeend >= ? AND timeend < ?))
                        AND deleted = 0 
                        AND status = $status
                        AND username = '$USER->username'
                        $campussql
                        ORDER BY timestart ASC";
            } else if ($status == locallib::ACTIVITY_STATUS_INREVIEW || $status == locallib::ACTIVITY_STATUS_APPROVED) {
                // Get all activities with this status.
                $sql = "SELECT *
                        FROM {excursions} 
                        WHERE ((timestart >= ? AND timestart < ?) OR (timeend >= ? AND timeend < ?))
                        AND deleted = 0 
                        AND status = $status
                        $campussql
                        $usersql
                        ORDER BY timestart ASC";
            } else {
                // Get all activities that are not draft/autosave.
                $sql = "SELECT *
                        FROM {excursions} 
                        WHERE ((timestart >= ? AND timestart < ?) OR (timeend >= ? AND timeend < ?))
                        AND deleted = 0
                        AND (status = $approved OR status = $inreview OR (status = $draft AND username = '$USER->username'))
                        $campussql
                        $usersql
                        ORDER BY timestart ASC";
            }
        } else {
            // Normal staff member.
            if ($status == locallib::ACTIVITY_STATUS_APPROVED) {
                // Get all approved activities.
                $sql = "SELECT *
                        FROM {excursions} 
                        WHERE ((timestart >= ? AND timestart < ?) OR (timeend >= ? AND timeend < ?))
                        AND deleted = 0 
                        AND status = $status
                        $campussql
                        $usersql
                        ORDER BY timestart ASC";
            } else if ($status == locallib::ACTIVITY_STATUS_DRAFT || $status == locallib::ACTIVITY_STATUS_INREVIEW) {
                // Get draft/inreview activities for this user only.
                $sql = "SELECT *
                        FROM {excursions} 
                        WHERE ((timestart >= ? AND timestart < ?) OR (timeend >= ? AND timeend < ?))
                        AND deleted = 0 
                        AND status = $status
                        AND (
                            username = '$USER->username' OR
                            staffincharge = '$USER->username' OR 
                            planningstaffjson LIKE '%$USER->username,%' OR 
                            accompanyingstaffjson LIKE '%$USER->username,%'
                        )
                        $campussql
                        $usersql
                        ORDER BY timestart ASC";
            } else {
                // Get all approved activities + draft/inreview activities for this user only.
                $sql = "SELECT *
                        FROM {excursions} 
                        WHERE ((timestart >= ? AND timestart < ?) OR (timeend >= ? AND timeend < ?))
                        AND deleted = 0 
                        AND (status = $approved OR 
                        (
                            (status = $draft OR status = $inreview) AND (
                                username = '$USER->username' OR
                                staffincharge = '$USER->username' OR 
                                planningstaffjson LIKE '%$USER->username,%' OR 
                                accompanyingstaffjson LIKE '%$USER->username,%'
                            )
                        ))
                        $campussql
                        $usersql
                        ORDER BY timestart ASC";
            }
        }
        //echo "<pre>"; var_export($sql); exit;
        $activities = array();
        $records = $DB->get_records_sql($sql, array($currentstart, $currentend, $currentstart, $currentend));
        foreach ($records as $record) {
            $activity = new activity($record->id, $record);
            $activityexporter = new activity_exporter($activity, array('minimal' => true));
            $exported = $activityexporter->export($OUTPUT);
            $activities[] = $exported;
        }


        // Get calendar entries. Don't get anything if filtering by draft or review, as this is an activity workflow thing.

        // Formulate the areas conition.
        $areassql = '';
        if ($campus == 'ss') {
            $areassql = " AND categoriesjson LIKE '%Senior school%' ";
        } else if ($campus == 'ps') {
            $areassql = " AND categoriesjson LIKE '%Primary school%' ";
        }

        $statussql = '';
        $usersql = '';
        if ($user == 'self') {
            $usersql = "AND (creator = '$USER->username' OR owner = '$USER->username') ";
        } else {
            if ($status == $draft || $status == $inreview) { 
                $statussql = 'AND status = 0';
                $usersql = "AND (creator = '$USER->username' OR owner = '$USER->username') ";
            } else if ($status == $approved) { 
                $statussql = 'AND status = 1';
            } else {// Any
                $usersql = "AND ((creator = '$USER->username' OR owner = '$USER->username') OR  status = 1)";
            }
        }
        

        $sql = "SELECT * 
            FROM {excursions_events}
            WHERE isactivity = 0
            AND deleted = 0
            $statussql
            AND ((timestart >= ? AND timestart < ?) OR (timeend >= ? AND timeend < ?))
            $areassql
            $usersql
            ORDER BY timestart ASC
        ";

        //echo "<pre>"; var_export($sql); exit;
        $events = array();
        $records = $DB->get_records_sql($sql, array($currentstart, $currentend, $currentstart, $currentend));
        foreach ($records as $event) {
            $event = (object) static::export_event($event);
            $event->calentryonly = true;
            $events[] = $event;
        }

        // Merge and sort activities and events.
        $merged = array_merge($activities, $events);
        usort($merged, fn($a, $b) => strcmp($a->timestart, $b->timestart));

        return $merged;
    }

    public static function get_assessments($user) {
        global $DB, $OUTPUT, $USER;
    
        $events = array();
        $thisyear = strtotime(date('Y-1-1 00:00', time()));

        // Formulate the user condition.
        $usersql = '';
        if ($user == 'self') {
            $usersql = "AND (ee.creator = '$USER->username' OR ee.owner = '$USER->username') ";
        }

        // Get assessments that are activities.
        $draft = locallib::ACTIVITY_STATUS_DRAFT;
        $inreview = locallib::ACTIVITY_STATUS_INREVIEW;
        $approved = locallib::ACTIVITY_STATUS_APPROVED;
        $sql = "SELECT e.*, ee.id as eventid
                  FROM {excursions} e
                  INNER JOIN {excursions_events} ee ON e.id = ee.activityid
                 WHERE e.deleted = 0
                   AND ee.isactivity = 1
                   AND ee.assessment = 1
                   AND (e.timestart >= $thisyear OR e.timeend >= $thisyear)
                   $usersql
        ";
        $activities = array();
        $records = $DB->get_records_sql($sql, []);
        foreach ($records as $record) {
            $activity = new activity($record->id, $record);
            $activityexporter = new activity_exporter($activity, array('minimal' => true));
            $exported = $activityexporter->export($OUTPUT);
            // Use edit url instead of manage.
            $exported->editurl = new \moodle_url('/local/excursions/event.php', array('edit' => $record->eventid));
            $activities[] = $exported;
        }


        // Get cal entry only assessments.
        $sql = "SELECT ee.* 
            FROM {excursions_events} ee
            WHERE isactivity = 0
            AND deleted = 0
            AND assessment = 1
            AND (timestart >= $thisyear OR timeend >= $thisyear)
            $usersql
        ";
        $records = $DB->get_records_sql($sql, []);
        foreach ($records as $event) {
            $event = (object) static::export_event($event);
            $event->calentryonly = true;
            $events[] = $event;
        }

        // Merge and sort activities and events.
        $merged = array_merge($activities, $events);
        usort($merged, fn($a, $b) => strcmp($a->timestart, $b->timestart));

        return $merged;

    }


    public static function search($text) {
        global $DB, $OUTPUT, $USER;

        $draft = locallib::ACTIVITY_STATUS_DRAFT;
        $inreview = locallib::ACTIVITY_STATUS_INREVIEW;
        $approved = locallib::ACTIVITY_STATUS_APPROVED;
        $sql = "SELECT * 
                  FROM {excursions}
                 WHERE deleted = 0
                   AND (activityname LIKE ? OR username LIKE ? OR staffinchargejson LIKE ? OR planningstaffjson LIKE ?)
                   AND (status = $approved OR status = $inreview OR (status = $draft AND username = '$USER->username'))
              ORDER BY timestart DESC";
        $params = array();
        $params[] = '%'.$text.'%';
        $params[] = '%'.$text.'%';
        $params[] = '%'.$text.'%';
        $params[] = '%'.$text.'%';
        //echo "<pre>"; var_export($sql); var_export($params); exit;

        $activities = array();
        $records = $DB->get_records_sql($sql, $params);
        foreach ($records as $record) {
            $activity = new activity($record->id, $record);
            $activityexporter = new activity_exporter($activity, array('minimal' => true));
            $exported = $activityexporter->export($OUTPUT);
            $activities[] = $exported;
        }


        $events = array();
        $sql = "SELECT * 
            FROM {excursions_events}
            WHERE isactivity = 0
            AND deleted = 0
            AND (activityname LIKE ? OR creator LIKE ? OR ownerjson LIKE ?)
            ORDER BY timestart DESC
        ";
        $records = $DB->get_records_sql($sql, $params);
        foreach ($records as $event) {
            $event = (object) static::export_event($event);
            $event->calentryonly = true;
            $events[] = $event;
        }
    
        // Merge and sort activities and events.
        $merged = array_merge($activities, $events);
        usort($merged, fn($a, $b) => strcmp($a->timestart, $b->timestart));

        return $merged;

    }

    
    public static function get_all_events($current = '') {
        global $DB;

        if (empty($current)) {
            $current = date('Y-m', time());
        }
        $broken = explode('-', $current);
        $currentstart = strtotime($broken[0] . '-' . $broken[1] . '-1 00:00');
        if ($broken[1] == 12) {
            $currentend = strtotime($broken[0]+1 . '-1-1 00:00');
        } else {
            $currentend = strtotime($broken[0] . '-' . ($broken[1]+1) . '-1 00:00');
        }

        $sql = "SELECT * 
                FROM {excursions_events}
                WHERE timestart >= ? 
                AND timestart < ?
                AND deleted = 0
                ORDER BY timestart ASC
        ";
        $events = array();
        $records = $DB->get_records_sql($sql, array($currentstart, $currentend));
        foreach ($records as $event) {
            $events[] = static::export_event($event);
        }
        return $events;
    }

    public static function everything() {
        $currentstart = strtotime('2000-1-1 00:00');
        $currentend = strtotime('3000-1-1 00:00');
        return static::get_for_date_range($currentstart, $currentend);
    }

    public static function get_user_events($current = '') {
        global $DB, $USER;

        if (empty($current)) {
            $current = date('Y-m', time());
        }
        $broken = explode('-', $current);
        $currentstart = strtotime($broken[0] . '-' . $broken[1] . '-1 00:00');
        $currentend = strtotime($broken[0] . '-' . ($broken[1]+1) . '-1 00:00');

        $sql = "SELECT * 
                FROM {excursions_events}
                WHERE timestart >= ? 
                AND timestart < ?
                AND owner = ?
                AND deleted = 0
                ORDER BY timestart ASC
        ";
        $events = array();
        $records = $DB->get_records_sql($sql, array($currentstart, $currentend, $USER->username));
        foreach ($records as $event) {
            $events[] = static::export_event($event);
        }

        return $events;
    }

    public static function export_event($event) {
        
        $owner = json_decode($event->ownerjson, true);
        $owner = array_pop($owner);
        $areas = $event->areasjson ? json_decode($event->areasjson, true) : [];
        $dateDiff = intval(($event->timeend-$event->timestart)/60);
        $days = intval($dateDiff/60/24);
        $hours = intval($dateDiff/60%24);
        $minutes = $dateDiff%60;
        $duration = '';
        $duration .= $days ? $days . 'd ' : '';
        $duration .= $hours ? $hours . 'h ' : '';
        $duration .= $minutes ? $minutes . 'm ' : '';

        $approved = !!$event->status;
        if ($event->isactivity) {
            $activity = new activity($event->activityid);
            $approved = locallib::status_helper($activity->get('status'))->isapproved;
        }

        $usercanedit = eventlib::can_user_edit($event->id);

        return array(
            'id' => $event->id,
            'eventname' => $event->activityname,
            'createdreadabledate' => date('j M y', $event->timecreated),
            'timestart' => $event->timestart,
            'timeend' => $event->timeend,
            'timestartReadable' => date('g:ia', $event->timestart),
            'datestartReadable' => date('j M', $event->timestart),
            'timeendReadable' => date('g:ia', $event->timeend),
            'dateendReadable' => date('j M', $event->timeend),
            'dayStart' => date('j', $event->timestart),
            'monyear' => date('M Y', $event->timestart),
            'monyearEnd' => date('M Y', $event->timeend),
            'dayEnd' => date('j', $event->timeend),
            'dayStartSuffix' => date('S', $event->timestart),
            'dayEndSuffix' => date('S', $event->timeend),
            'duration' => $duration,
            'areas' => $areas,
            'details' => $event->notes,
            'shortdetails' => locallib::tokenTruncate($event->notes, 120),
            'owner' => $owner,
            'nonnegotiable' => $event->nonnegotiable,
            'editurl' => new \moodle_url('/local/excursions/event.php', array('edit' => $event->id)),
            'status' => $event->status,
            'syncon' => $approved,
            'pushpublic' => $event->pushpublic,
            'displaypublic' => $event->displaypublic,
            'location' => $event->location,
            'isactivity' => $event->isactivity,
            'isassessment' => $event->assessment,
            'assessmenturl' => $event->assessmenturl,
            'usercanedit' => $usercanedit,
        );
    }

    

    public static function check_conflicts($eventid, $timestart, $timeend, /*$recurringsettings = null,*/ $unix = false) {
        global $DB;

        if (!$unix) {
            $timestart = strtotime($timestart);
            $timeend = strtotime($timeend);
        }

        // If this event is recurring, need to check conflicts for all the dates in the series.
        /*if ($recurringsettings && $recurringsettings->recurring) {
            // Check if this is an existing event in a series.
            $recurrenceid = -1;
            $event = static::get_event($eventid);
            if (!empty($event)) {
                $recurrenceid = $event->recurrencemaster ? $event->recurrencemaster : -1;
            }

            if (!$unix) {
                $recurringsettings->recuruntil = strtotime($recurringsettings->recuruntil);
            }
            $dates = (object) static::expand_dates($recurringsettings, $timestart, $timeend);
            $conflicts = [];
            if ($dates) {
                foreach($dates->dates as $date) {
                    $conflicts = array_merge($conflicts, static::check_conflicts_for_single($eventid, $date['start'], $date['end'], $recurrenceid));
                }
            }
            return $conflicts;
        } else {*/
            return static::check_conflicts_for_single($eventid, $timestart, $timeend);
        //}
    }

    public static function check_conflicts_for_single($eventid, $timestart, $timeend, $recurrenceid = -1) {
        global $DB;
        $conflicts = array();

        // Find events that intersect with this start and end time.
        $sql = "SELECT * 
                FROM {excursions_events}
                WHERE deleted = 0 
                AND ((timestart > ? AND timestart < ?) 
                OR (timeend > ? AND timeend < ?)
                OR (timestart <= ? AND timeend >= ?) 
                OR (timestart >= ? AND timeend <= ?))
        ";
        $raweventconflicts = $DB->get_records_sql($sql, [$timestart, $timeend, $timestart, $timeend, $timestart, $timeend, $timestart, $timeend]);
        foreach ($raweventconflicts as $event) {
            // Dont clash with self or another event in the same series.
            if ($event->id == $eventid /*|| $event->recurrencemaster == $recurrenceid*/) {
                continue;
            }
            $owner = json_decode($event->ownerjson);
            $owner = array_pop($owner);
            $avatar = '<div><img class="rounded-circle" height="18" src="' . $owner->photourl . '"> <span>' . $owner->fullname . '</span></div>';
            $areas = json_decode($event->areasjson);
            $areas = "<ul>" . implode("", array_map(function($area) { return "<li>" . $area . "</li>"; }, $areas)) . "</ul>";
            $conflicts[] =  (object) [
                'eventid' => $event->id,
                'eventname' => $event->activityname,
                'location' => $event->location,
                'nonnegotiable' => $event->nonnegotiable,
                'eventtype' => 'event',
                'timestart' => '<div>' . date('g:ia', $event->timestart) . '</div><div><small>' . date('j M Y', $event->timestart) . '</small></div>',
                'timeend' => '<div>' . date('g:ia', $event->timeend) . '</div><div><small>' . date('j M Y', $event->timeend) . '</small></div>',
                'areas' => $areas,
                'owner' => $avatar,
            ];
        }

        // Find excursions that intersect with this start and end time.
        // If everything comes through as an event, no need to check activities separately.
        /*$sql = "SELECT * FROM {excursions}
                WHERE (timestart > ? AND timestart < ?) 
                OR (timeend > ? AND timeend < ?)
                OR (timestart <= ? AND timeend >= ?) 
                OR (timestart >= ? AND timeend <= ?) 
        ";
        $rawexcursionconflicts = $DB->get_records_sql($sql, [$timestart, $timeend, $timestart, $timeend, $timestart, $timeend, $timestart, $timeend]);
        foreach ($rawexcursionconflicts as $event) {
            $staffincharge = json_decode($event->staffinchargejson);
            $staffincharge = array_pop($staffincharge);
            $avatar = '<div><img class="rounded-circle" height="18" src="' . $staffincharge->photourl . '"> <span>' . $staffincharge->fullname . '</span></div>';
            $conflicts[] =  (object) [
                'eventid' => $event->id,
                'eventname' => $event->activityname,
                'eventtype' => 'excursion',
                'timestart' => '<div>' . date('g:ia', $event->timestart) . '</div><div><small>' . date('j M Y', $event->timestart) . '</small></div>',
                'timeend' => '<div>' . date('g:ia', $event->timeend) . '</div><div><small>' . date('j M Y', $event->timeend) . '</small></div>',
                'affected' => $event->campus,
                'owner' => $avatar,
                'status' => 0,
                'conflictid' => 0,
            ];
        }*/
        
        return $conflicts;
    }


    public static function check_conflicts_for_event($id) {
        global $DB;

        $event = static::get_event($id);
        if (empty($event)) {
            return [];
        }

        $conflicts = static::check_conflicts_for_single($id, $event->timestart, $event->timeend);
        static::sync_conflicts($id, $conflicts);
        $html = static::generate_conflicts_html($conflicts, true, static::export_event($event));
 
        return ['html' => $html, 'conflicts' => $conflicts];

    }

    public static function generate_conflicts_html($conflicts, $withActions = false, $eventContext = null) {
        $html = '';
        // Generate the html.
        if (count($conflicts)) {
            if ($eventContext) {
                $eventContext = (object) $eventContext;
                $owner = '<div><img class="rounded-circle" height="18" src="' . $eventContext->owner['photourl'] . '"> <span>' . $eventContext->owner['fullname'] . '</span></div>';
                $areas = "<ul>" . implode("", array_map(function($area) { return "<li>" . $area . "</li>"; }, $eventContext->areas)) . "</ul>";
                $timestart = '<div>' . $eventContext->timestartReadable . '</div><div><small>' . date('j M Y', $eventContext->timestart) . '</small></div>';
                $timeend = '<div>' . $eventContext->timeendReadable . '</div><div><small>' . date('j M Y', $eventContext->timeend) . '</small></div>';
                $html .= '<div class="table-heading"><b class="table-heading-label">Event summary</b></div>';
                $html .= "<table><tr> <th>Title</th> <th>Date</th> <th>Location</th> <th>Areas</th> <th>Owner</th> </tr>";
                $actionshtml = '';
                if ($withActions) {
                    $editurl = new \moodle_url('/local/excursions/event.php', array('edit' => $eventContext->id));
                    $actionshtml .= '<td><div class="actions">';
                    $actionshtml .= '<a class="btn btn-secondary" target="_blank" href="' . $editurl->out(false) . '">Edit</a><br><br>';
                    $actionshtml .= "</div></td>";
                }
                $html .= "<tr><td>$eventContext->eventname</td>";
                $html .= "<td><div style=\"display:flex;gap:20px;\"><div>$timestart</div><div>$timeend</div></div></td>";
                $html .= "<td>$eventContext->location</td>";
                $html .= "<td>$areas</td><td>$owner</td>$actionshtml</tr>";
                $html .= "</table><br>";
                $html .= '<div class="table-heading"><b class="table-heading-label">Conflicting events</b></div>';
            }
            $html .= "<table> <tr> <th>Title</th> <th>Dates</th> <th>Location</th> <th>Areas</th> <th>Owner</th> </tr>";
            foreach($conflicts as $conflict) {
                $nonneg =  $conflict->nonnegotiable ? '<br><small>Non-negotiable</small>' : '';
                $html .= '<tr data-eventid="' . $conflict->eventid . '" data-status="' . $conflict->status . '">';
                $html .= "<td>$conflict->eventname $nonneg</td>";
                $html .= "<td><div style=\"display:flex;gap:20px;\"><div>$conflict->timestart</div><div>$conflict->timeend</div></div></td>";
                $html .= "<td>$conflict->location</td>";
                $html .= "<td>$conflict->areas</td>";
                $html .= "<td>$conflict->owner</td>";
                if ($withActions) {
                    $editurl = new \moodle_url('/local/excursions/event.php', array('edit' => $conflict->eventid));
                    $html .= '<td><div class="actions">';
                    $html .= '<a class="btn btn-secondary" target="_blank" href="' . $editurl->out(false) . '">Edit</a><br><br>';
                    $html .= '<div>
                                <input type="checkbox" id="ignore' . $conflict->conflictid . '" data-conflictid="' . $conflict->conflictid . '" name="status" value="1"' . ($conflict->status == 1 ? 'checked="true"' : '') . '>
                                <label for="ignore' . $conflict->conflictid . '">Ignore conflict</label>
                              </div>';
                    $html .= "</div></td>";
                }
                $html .= "</tr>";
            }
            $html .= '</table>';
        }
        return $html;
    }

    public static function sync_conflicts($eventid, &$conflicts) {
        global $DB;

        // Sync found conflicts to db.
        // Get existing conflicts for this event.
        $existingConflicts = $DB->get_records_sql("
            SELECT * FROM {excursions_event_conflicts}
            WHERE eventid1 = ?
            OR eventid2 = ?
        ", [$eventid, $eventid]);

        // Process the newly found conflicts.
        foreach($conflicts as &$conflict) {
            // Check if a record already exists for this conflict.
            foreach ($existingConflicts as $i => $existing) {
                if ($existing->eventid1 == $conflict->eventid || $existing->eventid2 == $conflict->eventid) {
                    // This conflict already exists in the db.
                    $conflict->conflictid = $existing->id;
                    $conflict->status = $existing->status;
                    unset($existingConflicts[$i]);
                }
            }
        }

        // Insert the new conflicts.
        $createConflicts = [];
        foreach($conflicts as &$conflict) {
            if (empty($conflict->conflictid)) {
                // Make sure this conflict does not exist.
                $sql = "SELECT id 
                FROM {excursions_event_conflicts} 
                WHERE eventid1 = ? AND eventid2 = ? 
                OR eventid2 = ? AND eventid1 = ?";
                $exists = $DB->get_records_sql($sql, [$eventid, $conflict->eventid, $eventid, $conflict->eventid]);
                if (empty($exists)) {
                    $createConflicts[] = [
                        'eventid1' => $eventid,
                        'eventid2' => $conflict->eventid,
                        'event2istype' => $conflict->eventtype,
                        'status' => 0,
                    ];
                }
            }
        }
        $DB->insert_records('excursions_event_conflicts', $createConflicts);

        // Delete db conflicts that are no longer conflicts.
        foreach ($existingConflicts as $remaining) {
            $DB->execute("DELETE FROM {excursions_event_conflicts} WHERE id = ?", [$remaining->id]);
        }
    }

    public static function set_conflict_status($id, $status) {
        global $DB;

        $theConflict = $DB->get_record('excursions_event_conflicts', array('id' => $id));
        if (empty($theConflict)) {
            return;
        }

        $theConflict->status = $status;
        $DB->update_record('excursions_event_conflicts', $theConflict);
    }

    public static function set_sync_status($eventid, $syncon = 0) {
        global $DB;

        $theEvent = $DB->get_record('excursions_events', array('id' => $eventid));
        if (empty($theEvent)) {
            return;
        }

        $theEvent->status = $syncon;
        $theEvent->timemodified = time();
        $DB->update_record('excursions_events', $theEvent);
    }

    public static function set_push_public($eventid, $pushon = 0) {
        global $DB;

        $theEvent = $DB->get_record('excursions_events', array('id' => $eventid));
        if (empty($theEvent)) {
            return;
        }

        $theEvent->pushpublic = $pushon;
        $theEvent->timemodified = time();
        $DB->update_record('excursions_events', $theEvent);
    }

    public static function set_assessmenturl($eventid, $url) {
        global $DB, $USER;

        $theEvent = $DB->get_record('excursions_events', array('id' => $eventid));
        if (empty($theEvent)) {
            throw new \Exception('Event not found', 100);
        }

        if ($theEvent->owner == $USER->username || $theEvent->creator == $USER->username) {
            $theEvent->assessmenturl = $url;
            $theEvent->timemodified = time();
            $DB->update_record('excursions_events', $theEvent);
        } else {
            throw new \Exception('Only event owner can update', 100);
        }
    }


    public static function soft_delete($id) {
        global $DB, $USER;

        $theEvent = $DB->get_record('excursions_events', array('id' => $id));
        if (empty($theEvent)) {
            return;
        }

        $theEvent->deleted = 1;
        $theEvent->timemodified = time();
        $DB->update_record('excursions_events', $theEvent);


        // Delete corresponding activity.
        if ($theEvent->isactivity && $theEvent->activityid) {
            activity::soft_delete($theEvent->activityid);
        }
    }

    public static function get_day_cycle($datetime) {      
        $config = get_config('local_excursions');
        $externalDB = \moodle_database::get_driver_instance($config->dbtype, 'native', true);
        $externalDB->connect($config->dbhost, $config->dbuser, $config->dbpass, $config->dbname, '');
        $sql = $config->daycycleinfosql . ' :date';
        $params = array('date' => $datetime);
        $daycycleinfo = $externalDB->get_record_sql($sql, $params);
        if ($daycycleinfo->daynumber) {
            return "Term $daycycleinfo->term Week $daycycleinfo->weeknumber Day $daycycleinfo->daynumber";
        }
        return "";
    }

    public static function can_user_edit($eventid) {
        global $USER;

        $event = eventlib::get_event($eventid);
        if (!$event) {
            return false;
        }

        // Is this user allowed to edit this event?
        if (locallib::is_event_reviewer()) {
            return true;
        } else if ($event->owner == $USER->username || $event->creator == $USER->username) {
            return true;
        } else {
            // if this is an activity, check if user is a staff in charge or additional planning staff.
            if ($event->isactivity) {
                $activity = new activity($event->activityid);
                if ($USER->username == $activity->get('staffincharge')) {
                    return true;
                } else {
                    $planning = json_decode($activity->get('planningstaffjson'));
                    if ($planning) {
                        foreach ($planning as $user) {
                            if ($USER->username == $user->idfield) {
                                return true;
                                break;
                            }
                        }
                    }
                }
            }
        }

        return false;

    }

}