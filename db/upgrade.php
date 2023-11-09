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
 * Post installation and migration code.
 *
 * @package   local_excursions
 * @copyright 2021 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_local_excursions_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2021032504) {
        // Add absencesprocessed field.
        $table = new xmldb_table('excursions');
        $absencesprocessed = new xmldb_field('absencesprocessed', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 0, null, 'otherparticipants');
        if (!$dbman->field_exists($table, $absencesprocessed)) {
            $dbman->add_field($table, $absencesprocessed);
        }
    }

    if ($oldversion < 2021032505) {
        // Add campus field.
        $table = new xmldb_table('excursions');
        $campus = new xmldb_field('campus', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null, null, 'activityname');
        if (!$dbman->field_exists($table, $campus)) {
            $dbman->add_field($table, $campus);
        }
    }

    
    if ($oldversion < 2021041601) {
        $table = new xmldb_table('excursions');

        $activitytype = new xmldb_field('activitytype', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null, null, 'campus');
        if (!$dbman->field_exists($table, $activitytype)) {
            $dbman->add_field($table, $activitytype);
        }
    }

    if ($oldversion < 2021041602) {
        // Add skip field.
        $table = new xmldb_table('excursions_approvals');
        $skip = new xmldb_field('skip', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 0, null, 'invalidated');
        if (!$dbman->field_exists($table, $skip)) {
            $dbman->add_field($table, $skip);
        }
    }
    
    if ($oldversion < 2021041603) {
        // Add remindersprocessed field.
        $table = new xmldb_table('excursions');
        $remindersprocessed = new xmldb_field('remindersprocessed', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 0, null, 'absencesprocessed');
        if (!$dbman->field_exists($table, $remindersprocessed)) {
            $dbman->add_field($table, $remindersprocessed);
        }
    }

    if ($oldversion < 2022050201) {
        // Add classrollprocessed field.
        $table = new xmldb_table('excursions');
        $field = new xmldb_field('classrollprocessed', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 0, null, 'remindersprocessed');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
    }

    if ($oldversion < 2022083100) {
        // Add planningstaffjson field.
        $table = new xmldb_table('excursions');
        $field = new xmldb_field('planningstaffjson', XMLDB_TYPE_TEXT, null, null, null, null, null, 'staffinchargejson');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
    }


    if ($oldversion < 2022083101) {
        // Define table excursions_planning_staff to be created.
        $table = new xmldb_table('excursions_planning_staff');

        // Adding fields to table excursions_planning_staff.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('activityid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('username', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table excursions_planning_staff.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_activityid', XMLDB_KEY_FOREIGN, ['activityid'], 'excursions', ['id']);

        // Conditionally launch create table for excursions_planning_staff.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
    }

    if ($oldversion < 2022083102) {
        $table = new xmldb_table('excursions_approvals');
        $field = new xmldb_field('nominated', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null, 'username');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
    }

    if ($oldversion < 2022083109) {
        $table = new xmldb_table('excursions_events');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('creator', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('recurrencemaster', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('activityname', XMLDB_TYPE_CHAR, '500', null, XMLDB_NOTNULL, null, null);
        $table->add_field('activitytype', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $table->add_field('campus', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $table->add_field('location', XMLDB_TYPE_CHAR, '500', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timestart', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timeend', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('nonnegotiable', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('reason', XMLDB_TYPE_CHAR, '1000', null, XMLDB_NOTNULL, null, null);
        $table->add_field('categoriesjson', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('owner', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('ownerjson', XMLDB_TYPE_CHAR, '1000', null, XMLDB_NOTNULL, null, null);
        $table->add_field('areasjson', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('recurringjson', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('notes', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('deleted', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_recurrencemaster', XMLDB_KEY_FOREIGN, ['recurrencemaster'], 'excursions_event_series', ['id']);
        $table->add_index('timestart', XMLDB_INDEX_NOTUNIQUE, ['timestart']);
        $table->add_index('timeend', XMLDB_INDEX_NOTUNIQUE, ['timeend']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('excursions_events_areas');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('eventid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('area', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_eventid', XMLDB_KEY_FOREIGN, ['eventid'], 'excursions_events', ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('excursions_event_series');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('data', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('excursions_event_conflicts');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('eventid1', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('eventid2', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('event2istype', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $table->add_field('status', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2022083109, 'local', 'excursions');

    }

    if ($oldversion < 2023070300) {
        $table = new xmldb_table('excursions_events');
        $isactivity = new xmldb_field('isactivity', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 0, null, 'deleted');
        if (!$dbman->field_exists($table, $isactivity)) {
            $dbman->add_field($table, $isactivity);
        }

        $activityid = new xmldb_field('activityid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0, null, 'isactivity');
        if (!$dbman->field_exists($table, $activityid)) {
            $dbman->add_field($table, $activityid);
        }

        upgrade_plugin_savepoint(true, 2023070300, 'local', 'excursions');
    }

    if ($oldversion < 2023070301) {
        $table = new xmldb_table('excursions_events');
        $status = new xmldb_field('status', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0, null, 'deleted');
        if (!$dbman->field_exists($table, $status)) {
            $dbman->add_field($table, $status);
        }

        $timesynclive = new xmldb_field('timesynclive', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0, null, 'timemodified');
        if (!$dbman->field_exists($table, $timesynclive)) {
            $dbman->add_field($table, $timesynclive);
        }

        upgrade_plugin_savepoint(true, 2023070301, 'local', 'excursions');
    }

    if ($oldversion < 2023070302) {

        // Define table excursions_events_sync to be created.
        $table = new xmldb_table('excursions_events_sync');

        // Adding fields to table excursions_events_sync.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('eventid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('calendar', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('externalid', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('changekey', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('weblink', XMLDB_TYPE_CHAR, '400', null, XMLDB_NOTNULL, null, null);
        $table->add_field('status', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timesynced', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table excursions_events_sync.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_eventid', XMLDB_KEY_FOREIGN, ['eventid'], 'excursions_events', ['id']);

        // Conditionally launch create table for excursions_events_sync.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Excursions savepoint reached.
        upgrade_plugin_savepoint(true, 2023070302, 'local', 'excursions');
    }

    if ($oldversion < 2023080100) {

        // Define field timesyncplanning to be added to excursions_events.
        $table = new xmldb_table('excursions_events');
        $field1 = new xmldb_field('timesyncplanning', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timesynclive');

        // Conditionally launch add field timesyncplanning.
        if (!$dbman->field_exists($table, $field1)) {
            $dbman->add_field($table, $field1);
        }

        $field2 = new xmldb_field('displaypublic', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'areasjson');
        // Conditionally launch add field displaypublic.
        if (!$dbman->field_exists($table, $field2)) {
            $dbman->add_field($table, $field2);
        }

        // Excursions savepoint reached.
        upgrade_plugin_savepoint(true, 2023080100, 'local', 'excursions');
    }

    if ($oldversion < 2023091800) {
        $table = new xmldb_table('excursions_events');

        $activitytype = new xmldb_field('activitytype', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null, null, 'activityname');
        if (!$dbman->field_exists($table, $activitytype)) {
            $dbman->add_field($table, $activitytype);
        }
        // Excursions savepoint reached.
        upgrade_plugin_savepoint(true, 2023091800, 'local', 'excursions');
    }

    if ($oldversion < 2023091801) {

        // Define field assessment to be added to excursions_events.
        $table = new xmldb_table('excursions_events');
        $assessment = new xmldb_field('assessment', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timesyncplanning');
        $courseid = new xmldb_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'assessment');

        // Conditionally launch add field assessment.
        if (!$dbman->field_exists($table, $assessment)) {
            $dbman->add_field($table, $assessment);
        }

        // Conditionally launch add field courseid.
        if (!$dbman->field_exists($table, $courseid)) {
            $dbman->add_field($table, $courseid);
        }

        // Excursions savepoint reached.
        upgrade_plugin_savepoint(true, 2023091801, 'local', 'excursions');
    }

    if ($oldversion < 2023110900) {
        $table = new xmldb_table('excursions_events');

        $assessmenturl = new xmldb_field('assessmenturl', XMLDB_TYPE_CHAR, '200', null, XMLDB_NOTNULL, null, null, null, 'courseid');
        if (!$dbman->field_exists($table, $assessmenturl)) {
            $dbman->add_field($table, $assessmenturl);
        }
        // Excursions savepoint reached.
        upgrade_plugin_savepoint(true, 2023110900, 'local', 'excursions');
    }

    return true;
}
