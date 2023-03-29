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


    return true;
}
