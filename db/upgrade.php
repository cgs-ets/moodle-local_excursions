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

    return true;
}
