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
 * Provides {@link local_excursions\external\submit_permission} trait.
 *
 * @package   local_excursions
 * @category  external
 * @copyright 2021 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

namespace local_excursions\external;

defined('MOODLE_INTERNAL') || die();

use \local_excursions\persistents\activity;
use external_function_parameters;
use external_value;
use context_user;

require_once($CFG->libdir.'/externallib.php');

/**
 * Trait implementing the external function.
 */
trait submit_permission {

    /**
     * Describes the structure of parameters for the function.
     *
     * @return external_function_parameters
     */
    public static function submit_permission_parameters() {
        return new external_function_parameters([
            'permissionid' => new external_value(PARAM_INT, 'Permission ID'),
            'response' => new external_value(PARAM_INT, 'Bit response'),
        ]);
    }

    /**
     * submit_permission the form data.
     *
     * @param int $query The search query
     */
    public static function submit_permission($permissionid, $response) {
        global $USER;
        
        // Setup context.
        $context = \context_user::instance($USER->id);
        self::validate_context($context);

        // Validate params.
        self::validate_parameters(self::submit_permission_parameters(), compact('permissionid', 'response'));
       
        // Save.
        return activity::submit_permission($permissionid, $response);

    }

    /**
     * Describes the structure of the function return value.
     *
     * @return external_single_structure
     */
    public static function submit_permission_returns() {
         return new external_value(PARAM_INT, 'Result');
    }

}