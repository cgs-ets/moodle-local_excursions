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

use \local_excursions\libs\eventlib;
use \local_excursions\libs\graphlib;
use \local_excursions\persistents\activity;
use \local_excursions\locallib;

/**
 * The main scheduled task for notifications.
 *
 * @package   local_excursions
 * @copyright 2023 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cron_sync_events extends \core\task\scheduled_task {

    // Use the logging trait to get some nice, juicy, logging.
    use \core\task\logging_trait;

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('cron_sync_events', 'local_excursions');
    }

    /**
     * Execute the scheduled task.
     */
    public function execute() {
        global $DB;
        $config = get_config('local_excursions');
        // Get events that have been changed since last sync.
        $this->log_start("Looking for events that require sync (modified after last sync).");
        $sql = "SELECT *
                FROM {excursions_events}
                WHERE timesynclive < timemodified
                AND timestart > 1704068052"; //1 Jan 2024.
        $events = array_values($DB->get_records_sql($sql));
        foreach ($events as $event) {
            $sdt = date('Y-m-d H:i', $event->timestart);
            $this->log("Processing event $event->id: '$event->activityname', starting '$sdt'");
            $error = false;

            // Is this entry/event approved?
            $approved = (int) !!$event->status;
            if ($event->isactivity) {
                $activity = new activity($event->activityid);
                $approved = (int) locallib::status_helper($activity->get('status'))->isapproved;
            }

            if (!empty($config->livecalupn)) {
                $destinationCalendars = array($config->livecalupn);
            } else {
                $destinationCalendars = array();
                if (!$event->deleted && $approved) {
                    // Determine which calendars this event needs to go to based on category selection.
                    $categories = json_decode($event->areasjson);
                    $destinationCalendars = [];
                    foreach ($categories as $cat) {
                        if (in_array($cat, ['Whole School', 'Primary School', 'ELC', 'Northside', 'Red Hill'])) {
                            $destinationCalendars[] = 'cgs_calendar_ps@cgs.act.edu.au';
                        }
                        if (in_array($cat, ['Whole School', 'Senior School', 'Co-curricular', 'Website', 'Alumni'])) {
                            $destinationCalendars[] = 'cgs_calendar_ss@cgs.act.edu.au';
                        }
                    }
                    $destinationCalendars = array_unique($destinationCalendars);
                    $destinationCalendars = array_filter($destinationCalendars);
                    // If not already in something based on cats above, add it to SS.
                    if (empty($destinationCalendars)) {
                        $destinationCalendars[] = 'cgs_calendar_ss@cgs.act.edu.au';
                    }
                    $this->log("Event has the categories: " . implode(', ', $categories) . ". Event will sync to: " . implode(', ', $destinationCalendars), 2);
                } else {
                    $this->log("Event is deleted ($event->deleted) or unapproved ($approved)", 2);
                }
            }

            // Get existing sync entries.
            $externalevents = array();
            if (!empty($config->livecalupn)) {
                $sql = "SELECT *
                    FROM {excursions_events_sync}
                    WHERE eventid = ?
                    AND calendar = ?";
                $params = array($event->id, $config->livecalupn);
                $externalevents = $DB->get_records_sql($sql, $params);
            } else {
                $sql = "SELECT *
                    FROM {excursions_events_sync}
                    WHERE eventid = ?
                    AND (calendar = ? OR calendar = ?)";
                $params = array($event->id, 'cgs_calendar_ss@cgs.act.edu.au', 'cgs_calendar_ps@cgs.act.edu.au');
                $externalevents = $DB->get_records_sql($sql, $params);
            }

            foreach($externalevents as $externalevent) {
                $calIx = array_search($externalevent->calendar, $destinationCalendars);
                if ($search === false || $event->deleted || !$approved) {
                    // The event was deleted, or entry not in a valid destination calendar, delete.
                    try {
                        $this->log("Deleting existing entry in calendar $externalevent->calendar", 2);
                        $result = graphlib::deleteEvent($externalevent->calendar, $externalevent->externalid);
                    } catch (\Exception $e) {
                        $this->log("Failed to delete event in calendar $externalevent->calendar: " . $e->getMessage(), 3);
                    }
                    $this->log("Removing event $externalevent->eventid from sync table", 3);
                    $DB->delete_records('excursions_events_sync', array('id' => $externalevent->id));
                } else {
                    $destCal = $destinationCalendars[$calIx];
                    // Entry in a valid destination calendar, update entry.
                    $this->log("Updating existing entry in calendar $destCal", 2);
                    $categories = json_decode($event->areasjson);
                    // Public will only be added to SS cal.
                    if ($destCal == 'cgs_calendar_ss@cgs.act.edu.au' && $event->displaypublic && $approved) {
                        $categories = $this->make_public_categories($categories);
                    }
                    // Update calendar event
                    $eventdata = new \stdClass();
                    $eventdata->subject = $event->activityname;
                    $eventdata->body = new \stdClass();
                    $eventdata->body->contentType = "HTML";
                    $eventdata->body->content = $event->notes;
                    if (!empty($categories)) {
                        $eventdata->categories = $categories;
                    }
                    $eventdata->start = new \stdClass();
                    $eventdata->start->dateTime = date('Y-m-d\TH:i:s', $event->timestart); 
                    $eventdata->start->timeZone = "AUS Eastern Standard Time";
                    $eventdata->end = new \stdClass();
                    $eventdata->end->dateTime = date('Y-m-d\TH:i:s', $event->timeend);
                    $eventdata->end->timeZone = "AUS Eastern Standard Time";
                    $eventdata->location = new \stdClass();
                    $eventdata->location->displayName = $event->location;
                    $eventdata->showAs = $approved ? 'busy' : 'tentative';
                    if (strpos($eventdata->start->dateTime, 'T00:00:00') !== false &&
                        strpos($eventdata->end->dateTime, 'T00:00:00') !== false) {
                        $eventdata->isAllDay = true;
                    }
                    try {
                        $result = graphlib::updateEvent($destCal, $externalevent->externalid, $eventdata);
                        unset($destinationCalendars[$calIx]);
                    } catch (\Exception $e) {
                        $this->log("Failed to update event in calendar $externalevent->calendar: " . $e->getMessage(), 3);
                        $this->log("Cleaning event $externalevent->eventid from sync table", 3);
                        $DB->delete_records('excursions_events_sync', array('id' => $externalevent->id));
                        $error = true;
                    }
                }
            }

            // Create entries in remaining calendars. There won't be any dest cals if the event was deleted.
            foreach($destinationCalendars as $destCal) {
                $this->log("Creating new entry in calendar $destCal", 2);
                $categories = json_decode($event->areasjson);
                $categories = $approved && $event->displaypublic ? $this->make_public_categories($categories) : $categories;
                // Create calendar event
                $eventdata = new \stdClass();
                $eventdata->subject = $event->activityname;
                $eventdata->body = new \stdClass();
                $eventdata->body->contentType = "HTML";
                $eventdata->body->content = $event->notes;
                if (!empty($categories)) {
                    $eventdata->categories = $categories;
                }
                $eventdata->start = new \stdClass();
                $eventdata->start->dateTime = date('Y-m-d\TH:i:s', $event->timestart); 
                $eventdata->start->timeZone = "AUS Eastern Standard Time";
                $eventdata->end = new \stdClass();
                $eventdata->end->dateTime = date('Y-m-d\TH:i:s', $event->timeend);
                $eventdata->end->timeZone = "AUS Eastern Standard Time";
                $eventdata->location = new \stdClass();
                $eventdata->location->displayName = $event->location;
                $eventdata->isOnlineMeeting = false;
                $eventdata->showAs = $approved ? 'busy' : 'tentative';
                if (strpos($eventdata->start->dateTime, 'T00:00:00') !== false &&
                    strpos($eventdata->end->dateTime, 'T00:00:00') !== false) {
                    $eventdata->isAllDay = true;
                }

                $record = new \stdClass();
                $record->eventid = $event->id;
                $record->calendar = $destCal;
                $record->timesynclive = time();
                $record->externalid = '';
                $record->changekey = '';
                $record->weblink = '';
                $record->status = 0;
                try {
                    $result = graphlib::createEvent($destCal, $eventdata);
                    if ($result) {
                        $record->externalid = $result->getId();
                        $record->changekey = $result->getChangeKey();
                        $record->weblink = $result->getWebLink();
                        $record->status = 1;
                    }
                } catch (\Exception $e) {
                    $this->log("Failed to insert event into calendar $externalevent->calendar: " . $e->getMessage(), 3);
                    $this->log(json_encode($eventdata), 3);
                    $error = true;
                }
                $id = $DB->insert_record('excursions_events_sync', $record);
            }

            $event->timesynclive = time();
            if ($error) {
                $event->timesynclive = -1;
            }
            if (!$approved) {
                $event->timesynclive = 0;
            }
            $DB->update_record('excursions_events', $event);

        }
        $this->log_finish("Finished syncing events.");  
    }

    private function make_public_categories($categories) {
        // Some categories need 'public' appended.
        $publiccats = ['Primary School', 'Senior School', 'Whole School', 'ELC', 'Red Hill', 'Northside', 'Website', 'Alumni'];
        $categories = array_map(function($cat) use ($publiccats) {
            if (in_array($cat, $publiccats)) {
                return [$cat, $cat . ' Public'];
            }
        }, $categories);
        $categories = call_user_func_array('array_merge', $categories);
        $categories = array_values(array_unique($categories));
        return $categories;
    }

    
    public function can_run(): bool {
        return true;
    }

}
