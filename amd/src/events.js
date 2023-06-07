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
        self.eventrows = self.rootel.find('table.events tr');

        self.eventrows = document.querySelectorAll('table.events tr');



    }

    /**
     * Run the Audience Selector.
     *
     */
   Events.prototype.main = function () {
      var self = this;

      // Start checking for conflicts, row by row.
      self.eventrows.length && self.getForNextRow(-1)
    };

    Events.prototype.getForNextRow = function(i) {
      var self = this;
      var next = i+1;
      if (self.eventrows.length >= next) {
        self.getConflictsForRow(self.eventrows[next].dataset.eventid, next)
      }
    }

    Events.prototype.getConflictsForRow = function (eventid, i) {
      var self = this;

      Log.debug("Getting conflicts for row " + i + " eventid " + self.eventrows[i].dataset.eventid);

      self.eventrows[i].classList.remove("conflicts-checked");
      self.eventrows[i].classList.remove("has-conflicts");
      self.eventrows[i].classList.remove("no-conflicts");
      self.eventrows[i].dataset.conflicts = '';
      self.eventrows[i].classList.add("checking-conflicts");

      Ajax.call([{
        methodname: 'local_excursions_formcontrol',
        args: { 
            action: 'check_conflicts_for_event',
            data: eventid,
        },
        done: function(response) {
          let data = JSON.parse(response);
          self.eventrows[i].classList.remove("checking-conflicts");
          self.eventrows[i].classList.add("conflicts-checked");
          if (data.hasConflicts) {
            self.eventrows[i].classList.add("has-conflicts");
            self.eventrows[i].dataset.conflicts = data.html;
          } else {
            self.eventrows[i].classList.add("no-conflicts");
          }
          self.getForNextRow(i)
        },
        fail: function(reason) {
          self.eventrows[i].classList.remove("checking-conflicts");
          self.eventrows[i].classList.add("conflicts-checked");
          Log.error('local_excursions/events: failed to check conflicts for event ' + eventid);
          Log.debug(reason);
          self.getForNextRow(i)
        }
      }]);
    }

    return {
        init: init
    };
});