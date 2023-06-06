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
 * Provides the local_excursions/events module
 *
 * @package   local_excursions
 * @category  output
 * @copyright 2023 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module local_excursions/Events
 */
define(['jquery', 'core/log', 'core/ajax', 'core/str' ], 
    function($, Log, Ajax, Str) {    
    'use strict';

    /**
     * Initializes the Events component.
     */
    function init() {
        Log.debug('local_excursions/events: initializing');

        var rootel = $('#page-local-excursions-events');

        if (!rootel.length) {
            Log.error('local_excursions/events: #page-local-excursions-events not found!');
            return;
        }

        var events = new Events(rootel);
        events.main();
    }

    /**
     * The constructor
     *
     * @constructor
     * @param {jQuery} rootel
     */
    function Events(rootel) {
        var self = this;
        self.rootel = rootel;
    }

    /**
     * Run the Audience Selector.
     *
     */
   Events.prototype.main = function () {
        var self = this;

    };

    return {
        init: init
    };
});