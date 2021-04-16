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
 * Plugin external functions and services are defined here.
 *
 * @package   local_excursions
 * @category  external
 * @copyright 2021 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_excursions_formcontrol' => [
        'classname'     => 'local_excursions\external\api',
        'methodname'    => 'formcontrol',
        'classpath'     => '',
        'description'   => 'Form control',
        'type'          => 'write',
        'loginrequired' => true,
        'ajax'          => true,
    ],
    'local_excursions_get_recipient_users' => [
        'classname'     => 'local_excursions\external\api',
        'methodname'    => 'get_recipient_users',
        'classpath'     => '',
        'description'   => 'Get\'s a list of users for the recipient selector',
        'type'          => 'read',
        'loginrequired' => true,
        'ajax'          => true,
    ],
    'local_excursions_submit_permission' => [
        'classname'     => 'local_excursions\external\api',
        'methodname'    => 'submit_permission',
        'classpath'     => '',
        'description'   => 'Save parent response to permission request',
        'type'          => 'write',
        'loginrequired' => true,
        'ajax'          => true,
    ],
];