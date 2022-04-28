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
 * @module local_excursions/Index
 */
define(['jquery', 'core/log', 'core/ajax', 'core/str' ], 
    function($, Log, Ajax, Str) {    
    'use strict';

    /**
     * Initializes the Index component.
     */
    function init() {
        Log.debug('local_excursions/index: initializing');

        var rootel = $('#page-local-excursions-index');

        if (!rootel.length) {
            Log.error('local_excursions/index: #page-local-excursions-index not found!');
            return;
        }

        var index = new Index(rootel);
        index.main();
    }

    /**
     * The constructor
     *
     * @constructor
     * @param {jQuery} rootel
     */
    function Index(rootel) {
        var self = this;
        self.rootel = rootel;
    }

    /**
     * Run the Audience Selector.
     *
     */
   Index.prototype.main = function () {
        var self = this;

        // Tabs.
        self.rootel.on('click', '.activities-tab', function(e) {
          e.preventDefault();

          // Get the target.
          var ref = $(this).data('ref');

          // Remove the current selected.
          self.rootel.find('.activities-tab').removeClass('selected');
          self.rootel.find('.list-activities').removeClass('selected');

          // Show the tab.
          $(this).addClass('selected');
          self.rootel.find('.list-activities.' + ref).addClass('selected');
        });

        // Show past.
        self.rootel.on('change', 'input.show-past-activities', function(e) {
          if ($(this).is(':checked')) {
            self.rootel.addClass('show-past-activities');
          } else {
            self.rootel.removeClass('show-past-activities');
          }
        });

        // Delete activity.
        self.rootel.on('click', '.delete-activity', function(e) {
            e.preventDefault();

            var row = $(this).closest('tr.activity');
            var activityid = row.data('id');

            self.rootel.find('tr.activity[data-id="' + activityid + '"]').addClass('deleting');

            Ajax.call([{
                methodname: 'local_excursions_formcontrol',
                args: { 
                    action: 'delete_activity',
                    data: activityid,
                },
                done: function(html) {
                    self.rootel.find('tr.activity[data-id="' + activityid + '"]').remove();
                },
                fail: function(reason) {
                    Log.error('local_excursions/index: failed to delete activity.');
                    Log.debug(reason);
                }
            }]);
        });  
        
        self.rootel.find('input.show-past-activities').change();

    };

    return {
        init: init
    };
});