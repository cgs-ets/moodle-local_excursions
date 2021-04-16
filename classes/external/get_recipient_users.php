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
 * Provides {@link local_excursions\external\get_recipient_users} trait.
 *
 * @package   local_excursions
 * @category  external
 * @copyright 2021 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

namespace local_excursions\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/externallib.php');

use context_user;
use external_function_parameters;
use external_value;
use invalid_parameter_exception;
use external_multiple_structure;
use external_single_structure;
use \local_excursions\locallib;

/**
 * Trait implementing the external function local_excursions_get_recipient_users.
 */
trait get_recipient_users {

    /**
     * Describes the structure of parameters for the function.
     *
     * @return external_function_parameters
     */
    public static function get_recipient_users_parameters() {
        return new external_function_parameters([
            'query' => new external_value(PARAM_RAW, 'The search query'),
            'role' => new external_value(PARAM_RAW, 'The user role'),
        ]);
    }

    /**
     * Gets a list of announcement users
     *
     * @param string $query The search query
     * @param string $role The user role
     */
    public static function get_recipient_users($query, $role) {
        global $USER;

        // Setup context.
        $context = \context_user::instance($USER->id);
        self::validate_context($context);

        // Validate params.
        self::validate_parameters(self::get_recipient_users_parameters(), compact('query', 'role'));
       
        // Get the users.
        $users = locallib::search_recipients($query, $role);

        return $users;
    }

    /**
     * Describes the structure of the function return value.
     *
     * @return external_single_structure
     */
    public static function get_recipient_users_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'idfield' => new external_value(PARAM_RAW, 'The user\'s username or course shortname'),
                    'fullname' => new external_value(PARAM_RAW, 'The user\'s full name'),
                    'idhighlighted' => new external_value(PARAM_RAW, 'The username with highlighted search text'),
                    'fullnamehighlighted' => new external_value(PARAM_RAW, 'The user\'s full name with highlighted search text'),
                    'photourl' => new external_value(PARAM_RAW, 'The user\'s photo src'),
                )
            )
        );
    }

}