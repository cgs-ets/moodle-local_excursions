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

        // Get events that have been changed since last sync.
        $this->log_start("Looking for events that require sync (modified after last sync).");
        //$sql = "SELECT *
        //        FROM {excursions_events}
        //        WHERE (status = 1 AND timesynclive < timemodified)
        //        OR (status = 0 AND timesynclive > 0)";
        $sql = "SELECT *
                FROM {excursions_events}
                WHERE timesynclive < timemodified";
        $events = $DB->get_records_sql($sql);

        $config = get_config('local_excursions');
        if (empty($config->livecalupn)) {
            return;
        }           

        foreach ($events as $event) {
            $this->log("Processing event $event->id: `$event->activityname`");
            $error = false;

            // Is this entry/event approved?
            $approved = !!$event->status;
            if ($event->isactivity) {
                $activity = new activity($event->activityid);
                $approved = locallib::status_helper($activity->get('status'))->isapproved;
            }

            $destinationCalendars = array();
            if (!$event->deleted && !!$event->status) {
                /*
                // Determine which calendars this event needs to go to based on category selection.
                $categories = json_decode($event->areasjson);
                $destinationCalendars = array_map(function($cat) {
                    if (in_array($cat, ['Whole School', 'Primary School', 'ELC', 'Northside', 'Red Hill'])) {
                        return 'cgs_calendar_ps@cgs.act.edu.au';
                    }
                    if (in_array($cat, ['Whole School', 'Senior School', 'Website', 'Alumni'])) {
                        return 'cgs_calendar_ss@cgs.act.edu.au';
                    }
                }, $categories);
                $destinationCalendars = array_unique($destinationCalendars);
                $this->log("Event has the categories: " . implode(', ', $categories) . ". Event will sync to: " . implode(', ', $destinationCalendars), 2);
                */
                $destinationCalendars = array($config->livecalupn);
            }

            // Get existing sync entries.
            $sql = "SELECT *
                FROM {excursions_events_sync}
                WHERE eventid = ?";
            $externalevents = $DB->get_records_sql($sql, [$event->id]);

            foreach($externalevents as $externalevent) {
                $search = array_search($externalevent->calendar, $destinationCalendars);
                if ($search === false || $event->deleted || !$event->status) {
                    try {
                        // Event deleted or entry not in a valid destination calendar, delete.
                        $this->log("Deleting existing entry in calendar $externalevent->calendar", 2);
                        $result = graphlib::deleteEvent($externalevent->calendar, $externalevent->externalid);
                    } catch (\Exception $e) {
                        $this->log("Failed to delete event in calendar $externalevent->calendar: " . $e->getMessage(), 3);
                        $this->log("Cleaning event $externalevent->eventid from sync table", 3);
                        $DB->delete_records('excursions_events_sync', array('id' => $externalevent->id));
                    }
                } else {
                    $destCal = $destinationCalendars[$search];
                    // Entry in a valid destination calendar, update entry.
                    $this->log("Updating existing entry in calendar $destCal", 2);
                    $categories = json_decode($event->areasjson);
                    // Update calendar event
                    $eventdata = new \stdClass();
                    $eventdata->subject = $event->activityname;
                    $eventdata->body = new \stdClass();
                    $eventdata->body->contentType = "HTML";
                    $eventdata->body->content = $event->notes;
                    $eventdata->categories = $approved && $event->displaypublic ? $this->make_public_categories($categories) : $categories;
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
                        unset($destinationCalendars[$search]);
                    } catch (\Exception $e) {
                        $this->log("Failed to update event in calendar $externalevent->calendar: " . $e->getMessage(), 3);
                        $this->log("Cleaning event $externalevent->eventid from sync table", 3);
                        $DB->delete_records('excursions_events_sync', array('id' => $externalevent->id));
                        $error = true;
                    }
                }
            }

            // Create entries in remaining calendars.
            foreach($destinationCalendars as $destCal) {
                $this->log("Creating new entry in calendar $destCal", 2);
                $categories = json_decode($event->areasjson);
                // Create calendar event
                $eventdata = new \stdClass();
                $eventdata->subject = $event->activityname;
                $eventdata->body = new \stdClass();
                $eventdata->body->contentType = "HTML";
                $eventdata->body->content = $event->notes;
                $eventdata->categories = $approved && $event->displaypublic ? $this->make_public_categories($categories) : $categories;
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
                    $this->log("Failed to insert event into calendar $externalevent->calendar", 3);
                    $error = true;
                }
                $id = $DB->insert_record('excursions_events_sync', $record);
            }

            $event->timesynclive = time();
            if ($error) {
                $event->timesynclive = -1;
            }
            if (!$event->status) {
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

}
