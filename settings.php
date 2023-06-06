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
 * Plugin administration pages are defined here.
 *
 * @package     local_excursions
 * @category    admin
 * @copyright   2021 Michael Vangelovski
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

	$settings = new admin_settingpage('local_excursions', get_string('pluginname', 'local_excursions'));
    $ADMIN->add('localplugins', $settings);

	$settings->add(new admin_setting_heading(
        'local_excursions_exdbheader', 
        get_string('settingsheaderdb', 'local_excursions'), 
        ''
    ));

	$options = array('', "mysqli", "oci", "pdo", "pgsql", "sqlite3", "sqlsrv");
    $options = array_combine($options, $options);
    $settings->add(new admin_setting_configselect(
        'local_excursions/dbtype', 
        get_string('dbtype', 'local_excursions'), 
        get_string('dbtype_desc', 'local_excursions'), 
        '', 
        $options
    ));

    $settings->add(new admin_setting_configtext('local_excursions/dbhost', get_string('dbhost', 'local_excursions'), get_string('dbhost_desc', 'local_excursions'), 'localhost'));

    $settings->add(new admin_setting_configtext('local_excursions/dbuser', get_string('dbuser', 'local_excursions'), '', ''));

    $settings->add(new admin_setting_configpasswordunmask('local_excursions/dbpass', get_string('dbpass', 'local_excursions'), '', ''));

    $settings->add(new admin_setting_configtext('local_excursions/dbname', get_string('dbname', 'local_excursions'), '', ''));

    $settings->add(new admin_setting_configtext('local_excursions/usertaglistssql', get_string('usertaglistssql', 'local_excursions'), '', ''));
    $settings->add(new admin_setting_configtext('local_excursions/publictaglistssql', get_string('publictaglistssql', 'local_excursions'), '', ''));
    $settings->add(new admin_setting_configtext('local_excursions/taglistuserssql', get_string('taglistuserssql', 'local_excursions'), '', ''));
    $settings->add(new admin_setting_configtext('local_excursions/checkabsencesql', get_string('checkabsencesql', 'local_excursions'), '', ''));
    $settings->add(new admin_setting_configtext('local_excursions/createabsencesql', get_string('createabsencesql', 'local_excursions'), '', ''));
    $settings->add(new admin_setting_configtext('local_excursions/studentdatachecksql', get_string('studentdatachecksql', 'local_excursions'), '', ''));
    $settings->add(new admin_setting_configtext('local_excursions/excursionconsentsql', get_string('excursionconsentsql', 'local_excursions'), '', ''));
    $settings->add(new admin_setting_configtext('local_excursions/deleteabsencessql', get_string('deleteabsencessql', 'local_excursions'), '', ''));

    $settings->add(new admin_setting_configtext('local_excursions/createclasssql', get_string('createclasssql', 'local_excursions'), '', ''));
    $settings->add(new admin_setting_configtext('local_excursions/insertclassstaffsql', get_string('insertclassstaffsql', 'local_excursions'), '', ''));
    $settings->add(new admin_setting_configtext('local_excursions/insertclassstudentsql', get_string('insertclassstudentsql', 'local_excursions'), '', ''));
    $settings->add(new admin_setting_configtext('local_excursions/deleteclassstudentssql', get_string('deleteclassstudentssql', 'local_excursions'), '', ''));

    $settings->add(new admin_setting_configtext('local_excursions/getterminfosql', get_string('getterminfosql', 'local_excursions'), '', ''));

    $settings->add(new admin_setting_configtext('local_excursions/eventreviewers', 'Event reviewers', 'Comma-separated usernames', ''));

}
