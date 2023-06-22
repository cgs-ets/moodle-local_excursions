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


class eventlib {

    public static function get_event($id) {
        global $DB;

        if (empty($id)) {
            return new \stdClass();
        }

        $event = $DB->get_record('excursions_events', array('id' => $id));

        if (empty($event)) {
            return false;
        }

        return $event;
    }

    public static function save_event($formdata) {
        global $DB, $USER;

        $event = eventlib::get_event($formdata->edit);
        if ($formdata->edit && $event === false) {
            // Editing but no event found. Major error.
            return;
        } else {
            $event->creator = $USER->username;
        }

        $event->activityname = $formdata->activityname;
        $event->campus = $formdata->campus;
        $event->location = $formdata->location;
        $event->timestart = $formdata->timestart;
        $event->timeend = $formdata->timeend;
        $event->nonnegotiable = $formdata->nonnegotiable;
        $event->reason = isset($formdata->nonnegotiablereason) ? $formdata->nonnegotiablereason : '';
        $event->notes = $formdata->notes;
        $event->categoriesjson = $formdata->categoriesjson;
        $event->areasjson = $formdata->areasjson;
        $event->ownerjson = $formdata->ownerjson;
        $event->owner = $USER->username;
        $event->recurringjson = '';
        $owner = json_decode($formdata->ownerjson);
        if ($owner) {
            $owner = array_pop($owner);
            $event->owner = $owner->idfield;
        }

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

        } else {
            // Single event. If it was previously in a series it will be detached.
            $event->recurrencemaster = 0;
            // Simple single event.            
            if (empty($event->id)) {
                $DB->insert_record('excursions_events', $event);
            } else {
                $DB->update_record('excursions_events', $event);
            }
        }

       
    }

    public static function create_new_series_from_data($event, $dates) {
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
    }

    
    public static function get_all_events($current = '') {
        global $DB;

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
                ORDER BY timestart ASC
        ";
        $events = array();
        $records = $DB->get_records_sql($sql, array($currentstart, $currentend));
        foreach ($records as $event) {
            $events[] = static::export_event($event);
        }
        return $events;
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
        //$categoriesjson = json_decode($event->categoriesjson);
        return array(
            'id' => $event->id,
            'eventname' => $event->activityname,
            'timestartReadable' => date('g:ia', $event->timestart),
            'datestartReadable' => date('j M', $event->timestart),
            'timeendReadable' => date('g:ia', $event->timeend),
            'dateendReadable' => date('j M', $event->timeend),
            'duration' => $duration,
            'areas' => $areas,
            'details' => $event->notes,
            'owner' => $owner,
            'nonnegotiable' => $event->nonnegotiable,
            'editurl' => new \moodle_url('/local/excursions/event.php', array('edit' => $event->id)),
            //'categoriesjson' => $categoriesjson,
        );
    }

    public static function check_conflicts($eventid, $timestart, $timeend, $recurringsettings = null, $unix = false) {
        global $DB;

        if (!$unix) {
            $timestart = strtotime($timestart);
            $timeend = strtotime($timeend);
        }

        // If this event is recurring, need to check conflicts for all the dates in the series.
        if ($recurringsettings && $recurringsettings->recurring) {
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
        } else {
            return static::check_conflicts_for_single($eventid, $timestart, $timeend);
        }
    }

    public static function check_conflicts_for_single($eventid, $timestart, $timeend, $recurrenceid = -1) {
        global $DB;
        $conflicts = array();

        // Find events that intersect with this start and end time.
        $sql = "SELECT * 
                FROM {excursions_events}
                WHERE (timestart > ? AND timestart < ?) 
                OR (timeend > ? AND timeend < ?)
                OR (timestart <= ? AND timeend >= ?) 
                OR (timestart >= ? AND timeend <= ?) 
        ";
        $raweventconflicts = $DB->get_records_sql($sql, [$timestart, $timeend, $timestart, $timeend, $timestart, $timeend, $timestart, $timeend]);
        foreach ($raweventconflicts as $event) {
            // Dont clash with self or another event in the same series.
            if ($event->id == $eventid || $event->recurrencemaster == $recurrenceid) {
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
                'eventtype' => 'event',
                'timestart' => '<div>' . date('g:ia', $event->timestart) . '</div><div><small>' . date('j M Y', $event->timestart) . '</small></div>',
                'timeend' => '<div>' . date('g:ia', $event->timeend) . '</div><div><small>' . date('j M Y', $event->timeend) . '</small></div>',
                'affected' => $areas,
                'owner' => $avatar,
            ];
        }

        // Find excursions that intersect with this start and end time.
        $sql = "SELECT * FROM {excursions}
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
        }
        
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
        $html = static::generate_conflicts_html($conflicts, true);
 
        return ['html' => $html, 'conflicts' => $conflicts];

    }

    public static function generate_conflicts_html($conflicts, $withActions = false) {
        $html = '';
        // Generate the html.
        if (count($conflicts)) {
            $html = "<table> <tr> <th>Title</th> <th>Start</th> <th>End</th> <th>Areas</th> <th>Owner</th> </tr>";
            foreach($conflicts as $conflict) {
                $html .= '<tr data-eventid="' . $conflict->eventid . '" data-status="' . $conflict->status . '">';
                $html .= "<td>" . $conflict->eventname . "</td>";
                $html .= "<td>" . $conflict->timestart . "</td>";
                $html .= "<td>" . $conflict->timeend . "</td>";
                $html .= "<td>" . $conflict->affected . "</td>";
                $html .= "<td>" . $conflict->owner . "</td>";
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
        ", [$eventid]);

        // Process the newly found conflicts.
        foreach($conflicts as &$conflict) {
            // Check if a record already exists for this conflict.
            foreach ($existingConflicts as $i => $existing) {
                if ($existing->eventid2 == $conflict->eventid && $existing->event2istype == $conflict->eventtype) {
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
                $exists = $DB->get_records_sql("SELECT id FROM {excursions_event_conflicts} WHERE eventid1 = ? AND eventid2 = ? AND event2istype = ?", [$eventid, $conflict->eventid, $conflict->eventtype]);
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

        $relatedconflicts = $DB->get_records_sql("
            SELECT * FROM {excursions_event_conflicts}
            WHERE eventid1 = $theConflict->eventid1 
            OR eventid1 = $theConflict->eventid2
            OR (eventid2 = $theConflict->eventid1 AND event2istype = 'event')
            OR (eventid2 = $theConflict->eventid2 AND event2istype = 'event')
        ");
        list($insql, $params) = $DB->get_in_or_equal(array_column($relatedconflicts, 'id'));
        $DB->execute("UPDATE {excursions_event_conflicts} SET status = ? WHERE id $insql", array_merge([$status],$params));
    }

}