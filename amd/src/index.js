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
 * @module local_excursions/index
 */
define(['jquery', 'core/log', 'core/ajax', 'core/str' ], 
    function($, Log, Ajax, Str) {    
    'use strict';

    /**
     * Initializes the index component.
     */
    function init() {
        Log.debug('local_excursions/index: initializing');

        var rootel = $('#page-content');

        if (!rootel.length) {
            Log.error('local_excursions/index: #page-content not found!');
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

    };

    return {
        init: init
    };
});