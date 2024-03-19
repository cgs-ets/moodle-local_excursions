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
 * Provides the local_excursions/permissions module
 *
 * @package   local_excursions
 * @category  output
 * @copyright 2021 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module local_excursions/Permissions
 */
define(['jquery', 'core/log', 'core/ajax', 'core/str' ], 
    function($, Log, Ajax, Str) {    
    'use strict';

    /**
     * Initializes the Permissions component.
     */
    function init() {
        Log.debug('local_excursions/permissions: initializing');

        var rootel = $('#page-local-excursions-permissions');

        if (!rootel.length) {
            Log.error('local_excursions/permissions: #page-local-excursions-permissions not found!');
            return;
        }

        var permissions = new Permissions(rootel);
        permissions.main();
    }

    /**
     * The constructor
     *
     * @constructor
     * @param {jQuery} rootel
     */
    function Permissions(rootel) {
        var self = this;
        self.rootel = rootel;
    }

    /**
     * Run the Audience Selector.
     *
     */
   Permissions.prototype.main = function () {
        var self = this;

        // Submit response
        self.rootel.on('change', 'input[name="permission"]', function(e) {
            var checkbox = $(this);
            self.submitPermission(checkbox);
        });

    };


    /**
     * Submit approve
     *
     * @method postComment
     */
    Permissions.prototype.submitPermission = function (checkbox) {
        var self = this;

        var response = checkbox.val();

        var permission = checkbox.closest('.permission');
        permission.find('.c-errormsg').html("");
        var permissionid = permission.data('id');

        permission.addClass('submitting');
        permission.attr('data-status', '0');
      
        Ajax.call([{
            methodname: 'local_excursions_submit_permission',
            args: { 
                permissionid: permissionid,
                response: response,
            },
            done: function(response) {
                var data = JSON.parse(response);
                permission.removeClass('submitting');
                permission.attr('data-status', response);

            },
            fail: function(reason) {
                permission.removeClass('submitting');
                Log.error('local_excursions/permissions: failed to submit permission.');
                Log.debug(reason);
                permission.find('.c-errormsg').html(reason);
            }
        }]);
    };


    return {
        init: init
    };
});