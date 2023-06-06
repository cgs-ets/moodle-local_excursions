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

        $event = $DB->get_record('event', array('id' => $id));

        if (empty($event)) {
            return false;
        }
    }

    public static function save_event($event) {
        global $DB;

        if (empty($event->id)) {
            $DB->insert_record('excursions_events', $event);
        } else {
            $DB->update_record('excursions_events', $event);
        }
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
        $hours = intval($dateDiff/60);
        $minutes = $dateDiff%60;
        $duration = $hours." Hours ".$minutes." Minutes";
        //$categories = json_decode($event->categories);
        return array(
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
            //'categories' => $categories,
        );
}

    public static function check_conflicts($timestart, $timeend) {
        global $DB;

        $hasConflicts = false;
        $html = 'No conflicts found';
        $conflicts = array();

        $timestart = strtotime($timestart);
        $timeend = strtotime($timeend);

        $sql = "SELECT * 
                FROM {excursions_events}
                WHERE (timestart > ? AND timestart < ?) 
                OR (timeend > ? AND timeend < ?)
                OR (timestart <= ? AND timeend >= ?) 
                OR (timestart >= ? AND timeend <= ?) 
        ";
        $raweventconflicts = $DB->get_records_sql($sql, [$timestart, $timeend, $timestart, $timeend, $timestart, $timeend, $timestart, $timeend]);
        foreach ($raweventconflicts as $event) {
            $owner = json_decode($event->ownerjson, true);
            $owner = array_pop($owner);
            $avatar = '<div><img class="rounded-circle" height="18" src="' . $owner->photourl . '"> <span>' . $owner->fullname . '</span></div>';
            $areas = json_decode($event->areasjson);
            $areas = "<ul>" . implode("", array_map(function($area) { return "<li>" . $area . "</li>"; }, $areas)) . "</ul>";
            $conflicts[] =  (object) [
                'eventname' => $event->activityname,
                'eventtype' => 'event',
                'timestart' => '<div>' . date('g:ia', $event->timestart) . '</div><div><small>' . date('j M Y', $event->timestart) . '</small></div>',
                'timeend' => '<div>' . date('g:ia', $event->timeend) . '</div><div><small>' . date('j M Y', $event->timeend) . '</small></div>',
                'affected' => $areas,
                'owner' => $avatar,
            ];
        }

        $sql = "SELECT * FROM {excursions}
        WHERE (timestart > ? AND timestart < ?) 
                OR (timeend > ? AND timeend < ?)
                OR (timestart <= ? AND timeend >= ?) 
                OR (timestart >= ? AND timeend <= ?) 
        ";
        $raweverawexcursionconflictsntconflicts = $DB->get_records_sql($sql, [$timestart, $timeend, $timestart, $timeend, $timestart, $timeend, $timestart, $timeend]);
        foreach ($rawexcursionconflicts as $event) {
            $staffincharge = json_decode($event->staffinchargejson, true);
            $staffincharge = array_pop($staffincharge);
            $avatar = '<div><img class="rounded-circle" height="18" src="' . $staffincharge->photourl . '"> <span>' . $staffincharge->fullname . '</span></div>';
            $conflicts[] =  (object) [
                'eventname' => $event->activityname,
                'eventtype' => 'excursion',
                'timestart' => '<div>' . date('g:ia', $event->timestart) . '</div><div><small>' . date('j M Y', $event->timestart) . '</small></div>',
                'timeend' => '<div>' . date('g:ia', $event->timeend) . '</div><div><small>' . date('j M Y', $event->timeend) . '</small></div>',
                'affected' => $event->campus,
                'owner' => $avatar,
            ];
        }

        if (count($conflicts)) {
            $hasConflicts = true;
            $html  = '<div class="conflicts-wrap">';
            $html .= '<div class="alert alert-warning"><strong>Review the conflicts below and consider whether your event needs to be moved before you continue.</strong></div>';
            $html .= "<table> <tr> <th>Title</th> <th>Start</th> <th>End</th> <th>Areas</th> <th>Owner</th> </tr>";
            foreach($conflicts as $conflict) {
                $html .= "<tr>";
                $html .= "<td>" . $conflict->eventname . "</td>";
                $html .= "<td>" . $conflict->timestart . "</td>";
                $html .= "<td>" . $conflict->timeend . "</td>";
                $html .= "<td>" . $conflict->affected . "</td>";
                $html .= "<td>" . $conflict->owner . "</td>";
                $html .= "</tr>";
            }
            $html .= '</table>';
            $html .= '</div>';
        }

        return ['hasConflicts' => $hasConflicts, 'html' => $html, 'rawdata' => $conflicts];
    }

}