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
 * Provides the local_excursions/index module
 *
 * @package   local_excursions
 * @category  output
 * @copyright 2021 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module local_excursions/assessments
 */
define(['jquery', 'core/log', 'core/ajax', 'core/str' ], 
    function($, Log, Ajax, Str) {    
    'use strict';

    /**
     * Initializes the index component.
     */
    function init() {
        Log.debug('local_excursions/assessments: initializing');

        var rootel = $('#page-content');

        if (!rootel.length) {
            Log.error('local_excursions/assessments: #page-content not found!');
            return;
        }

        var assessments = new Assessments(rootel);
        assessments.main();
    }

    /**
     * The constructor
     *
     * @constructor
     * @param {jQuery} rootel
     */
    function Assessments(rootel) {
        var self = this;
        self.rootel = rootel;
    }

    /**
     * Run the Audience Selector.
     *
     */
    Assessments.prototype.main = function () {
      var self = this;

      // Filters
      self.rootel.on('change', 'select.filter-select', function(e) {
        var val = $(this).find(":selected").val();
        var queryParams = new URLSearchParams(window.location.search);
        queryParams.set($(this).attr("name"), val);
        self.rootel.find('.excursions-overlay').addClass('active');
        window.location.href = '//' + location.host + location.pathname + "?" + queryParams.toString();
      });


      // Edit Link.
      self.rootel.on('click', '.btn-edit-link', function(e) {
        e.preventDefault();
        let btn = $(this);
        const currentUrl = btn.data('url');
        const eventid = btn.data('eventid');
        let newUrl = prompt('Assessment URL', currentUrl);
        if (newUrl !== null) {
          // Update Assessment URL.
          Ajax.call([{
            methodname: 'local_excursions_formcontrol',
            args: { 
                action: 'update_assessmenturl',
                
                data:  JSON.stringify({
                  eventid: eventid,
                  url: newUrl,
                })
            },
            done: function() {
              btn.data('url', newUrl);
              $('.assessmentlink[data-eventid="' + eventid + '"]').attr('href', newUrl);
            },
            fail: function(reason) {
              alert('Failed to update assessment url.');
              Log.error('local_excursions/assessments: failed to update assessment url.');
              Log.debug(reason);
            }
          }]);
        }
      }); 

    };

    return {
        init: init
    };
});