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
define(['jquery', 'core/log', 'core/ajax', 'core/modal_factory', 'core/modal_events' ], 
    function($, Log, Ajax, ModalFactory, ModalEvents) {    
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
        self.eventrows = document.querySelectorAll('table.events tr.event');
        self.eventrows = Array.from(self.eventrows);
        self.conflictignorechange = false;

        ModalFactory.create({type: ModalFactory.types.DEFAULT}).then(function(modal) {
          modal.setTitle('Conflicts found');
          self.modal = modal;
          self.modal.getModal().addClass('modal-xl');
          self.modal.getModal().addClass('modal-conflicts');
          self.modal.getRoot().on(ModalEvents.hidden, function(){
            if (self.conflictignorechange) {
              self.rootel.find('.excursions-overlay').addClass('active');
              location.reload();// reload page
            }
          });
        });

    }

    /**
     * Run the Audience Selector.
     *
     */
   Events.prototype.main = function () {
      var self = this;

      // Start checking for conflicts, row by row.
      if (self.eventrows.length) {
        self.getForNextRow(-1)
      }

      // Navigate from select.
      self.rootel.on('change', 'select.page-select', function(e) {
        var nav = $(this).find(":selected").val();
        var queryParams = new URLSearchParams(window.location.search);
        queryParams.set("nav", nav);
        self.rootel.find('.excursions-overlay').addClass('active');
        window.location.href = '//' + location.host + location.pathname + "?" + queryParams.toString();
      });

      // Nav chevrons
      self.rootel.on('click', 'a.page-link', function(e) {
        e.preventDefault();
        var queryParams = new URLSearchParams(window.location.search);
        queryParams.set("nav", $(this).data('nav'));
        self.rootel.find('.excursions-overlay').addClass('active');
        window.location.href = '//' + location.host + location.pathname + "?" + queryParams.toString();
      });

      // Filters
      self.rootel.on('change', 'select.filter-select', function(e) {
        var val = $(this).find(":selected").val();
        var queryParams = new URLSearchParams(window.location.search);
        queryParams.set($(this).attr("name"), val);
        self.rootel.find('.excursions-overlay').addClass('active');
        window.location.href = '//' + location.host + location.pathname + "?" + queryParams.toString();
      });

      document.querySelectorAll('.btn-showconflicts').forEach(a => {
        a.addEventListener('click', e => {
          e.preventDefault()
          //get the event conflicts from the table row data.
          let eventid = a.dataset.eventid;
          let tr = document.querySelector('tr[data-eventid="' + eventid + '"]');
          if (tr) {
            self.modal.setBody('<div class="conflicts-wrap">' + tr.dataset.conflicts + '</div>');
            self.modal.show()

            // Ignore checkbox action.
            document.querySelectorAll('.modal-conflicts input[name="status"]').forEach(a => {
              a.addEventListener('change', e => {
                self.conflictignorechange = true;
                Ajax.call([{
                  methodname: 'local_excursions_formcontrol',
                  args: { 
                    action: 'set_conflict_status',
                    data: JSON.stringify({
                      conflictid: e.currentTarget.dataset.conflictid,
                      status: e.currentTarget.checked
                    })
                  },
                }]);
              })
            })

          }
        })
      })

      // Ignore checkbox action.
      document.querySelectorAll('.cb-syncevent').forEach(input => {
        input.addEventListener('change', e => {
          let eventid = e.currentTarget.value
          let syncon = e.currentTarget.checked
          Ajax.call([{
            methodname: 'local_excursions_formcontrol',
            args: { 
              action: 'set_event_sync_status',
              data: JSON.stringify({
                eventid: eventid,
                syncon: syncon ? 1 : 0,
              })
            },
            done: function(response) {
              let tr = document.querySelector('tr[data-eventid="' + eventid + '"]');
              tr.dataset.status = syncon ? 1 : 0
            },
          }]);
        })
      })
      
    };

    Events.prototype.getForNextRow = function(i) {
      var self = this;
      var next = i+1;
      if (self.eventrows.length > next) {
        self.getConflictsForRow(self.eventrows[next].dataset.eventid, next)
      }
    }

    Events.prototype.getConflictsForRow = function (eventid, i) {
      var self = this;

      Log.debug("Getting conflicts for row " + i + " eventid " + self.eventrows[i].dataset.eventid);

      self.eventrows[i].classList.remove("conflicts-checked");
      self.eventrows[i].classList.remove("has-conflicts");
      self.eventrows[i].classList.remove("has-ignored-conflicts");
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
          if (data.conflicts.length) {
            let actionneeded = (data.conflicts.filter((c)=>c.status!=1).length > 0)
            if (actionneeded) {
              self.eventrows[i].classList.add("has-conflicts");
            } else {
              self.eventrows[i].classList.add("has-ignored-conflicts");
            }
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