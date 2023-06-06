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
 * Module for recipient autocomplete field.
 *
 * @package   local_excursions
 * @category  output
 * @copyright 2021 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module local_excursions/recipientselector
 */
define(['jquery', 'core/log', 'core/ajax', 'core/templates', 'core/str'], function($, Log, Ajax, Templates, Str) {
    'use strict';

    /**
     * Initializes the recipientselector component.
     */
    function init(selector, role, allowmultiple) {
        Log.debug('local_excursions/recipientselector: initializing the ' + '#' + selector + ' recipient selector component');

        var rootel = $('#' + selector).first();

        if (!rootel.length) {
            Log.error('local_excursions/recipientselector: ' + '#' + selector + ' root element not found!');
            return;
        }

        var recipientselector = new RecipientSelector(rootel, selector, role, allowmultiple);
        recipientselector.main();

        return recipientselector;
    }

    /**
     * The recipient selector constructor
     *
     * @constructor
     * @param {jQuery} rootel
     */
    function RecipientSelector(rootel, selector, role, allowmultiple) {
        var self = this;
        self.rootel = rootel;
        self.component = 'local_excursions';
        self.selector = selector;
        self.role = role;
        self.allowmultiple = allowmultiple;

        self.strings = {}
        Str.get_strings([
            {key: 'activityform:recipientnoselection', component: self.component},
        ]).then(function(s) {
            self.strings.noselectionstr = s[0];
        });
        if (!allowmultiple) {
          self.rootel.addClass('allow-single');
        }
    }

    /**
     * Run the Recipient Selector.
     *
     */
   RecipientSelector.prototype.main = function () {
        var self = this;

        // Render existing selection (if editing).
        self.render();

        // Handle search.
        var keytimer;
        self.rootel.on('keyup', '.recipient-autocomplete', function(e) {
            clearTimeout(keytimer);
            var autocomplete = $(this);
            if (e.which == 13) {
                self.search(autocomplete);
            } else {
                keytimer = setTimeout(function () {
                    self.search(autocomplete);
                }, 500);
            }
        });

        // Handle search result click.
        self.rootel.on('click', '.recipient-result', function(e) {
            e.preventDefault();
            var tag = $(this);
            self.add(tag);
        });

        // Handle tag click.
        self.rootel.on('click', '.recipient-tag', function(e) {
            e.preventDefault();
            var tag = $(this);
            self.remove(tag);
        });

        // Handle entering the autocomplete field.
        self.rootel.on('focus', '.recipient-autocomplete', function(e) {
            self.refocus();
        });

        // Handle leaving the autocomplete field.
        $(document).on('click', function (e) {
            var target = $(e.target);
            if (target.is('.recipient-autocomplete') || target.is('.recipient-result')) {
                return;
            }
            self.unfocus();
        });
    };


    /**
     * Add a selection.
     *
     * @method
     */
    RecipientSelector.prototype.add = function (tag) {
        var self = this;
        self.unfocus();

        self.rootel.addClass('adding-tag');

        var input = self.rootel.parent().find('input[name="' + self.selector + 'json"]').first();

        // Convert JSON into array.
        var tags = new Array();
        if (self.allowmultiple == 1) { // Allow multiple
            if(input.val()) {
                tags = JSON.parse(input.val());
            }

            // Ensure tag has not already been added.
            var i;
            for (i = 0; i < tags.length; i++) {
                if (tags[i]['idfield'] == tag.data('idfield')) {
                    self.render();
                    return;
                }
            }
        }

        // Create a new tag object and add tag to array.
        var obj = {
            taguid: Date.now(),
            idfield: tag.data('idfield'),
            photourl: tag.find('img').attr('src'),
            fullname: tag.find('span').first().text(),
        };
        tags.push(obj);

        // Encode to json and to hidden input.
        var json = JSON.stringify(tags);
        input.val(json);

        self.render();
    };

    /**
     * Remove a selection.
     *
     * @method
     */
    RecipientSelector.prototype.remove = function (tag) {
        var self = this;

        var removeuid = tag.data('taguid');
        if( ! removeuid ) {
            removeuid = '';
        }
        var input = self.rootel.parent().find('input[name="' + self.selector + 'json"]').first();
        var tags = JSON.parse(input.val());
        var tagsnew = new Array();
        var i;
        for (i = 0; i < tags.length; i++) {
            var curruid = tags[i]['taguid'];
            if( ! curruid ) {
                curruid = '';
            }
            if (curruid != removeuid) {
                tagsnew.push(tags[i]);
            }
        }
        var json = '';
        if (tagsnew.length) {
            json = JSON.stringify(tagsnew);
        } else {
            self.rootel.removeClass('has-tags');
        }
        input.val(json);

        tag.remove();
    };

    /**
     * Render the selection.
     *
     * @method
     */
    RecipientSelector.prototype.render = function () {
        var self = this;
        self.rootel.removeClass('has-tags');
        
        var input = self.rootel.parent().find('input[name="' + self.selector + 'json"]').first();

        var json = input.val();
        var tags = [];
        if (json) {
            tags = JSON.parse(json);
        }

        if (!tags.length) {
            self.rootel.find('.recipient-selection').html(self.strings.noselectionstr);
            return;
        }

        // Render the tag from a template.
        var data = {tags: tags};

        Templates.render('local_excursions/recipient_selector_tags', data)
            .then(function(html) {
                self.rootel.find('.recipient-selection').html(html);
                self.rootel.addClass('has-tags');
                self.rootel.removeClass('adding-tag');
                self.rootel.find('.recipient-autocomplete').val('');
            }).fail(function(reason) {
                Log.error(reason);
            });

    };

    /**
     * Search.
     *
     * @method
     */
    RecipientSelector.prototype.search = function (searchel) {
        var self = this;
        self.hasresults = false;

        if (searchel.val() == '') {
            return;
        }

        self.rootel.addClass('searching');

        Ajax.call([{
            methodname: 'local_excursions_get_recipient_users',
            args: { 
                query: searchel.val(),
                role: self.role,
            },
            done: function(response) {
                if (response.length) {
                    self.hasresults = true;
                    // Render the results.
                    Templates.render('local_excursions/recipient_selector_results', { users : response }) 
                        .then(function(html) {
                            var results = self.rootel.find('.recipient-results');
                            results.html(html);
                            self.rootel.addClass('showing-results');
                            results.addClass('active');
                            self.rootel.removeClass('searching');
                        }).fail(function(reason) {
                            Log.error(reason);
                        });
                } else {
                    self.rootel.removeClass('searching');
                    self.rootel.find('.recipient-results').removeClass('active');
                    self.rootel.removeClass('showing-results');
                }
            },
            fail: function(reason) {
                self.rootel.removeClass('searching');
                Log.error('local_excursions/recipientselector: failed to search.');
                Log.debug(reason);
            }
        }]);
    };

    /**
     * Leave the autocomplete field.
     *
     * @method
     */
    RecipientSelector.prototype.unfocus = function () {
        var self = this;
        self.rootel.find('.recipient-results').removeClass('active');
        self.rootel.removeClass('showing-results');
    };

    /**
     * Leave the autocomplete field.
     *
     * @method
     */
    RecipientSelector.prototype.refocus = function () {
        var self = this;
        if (self.rootel.find('.recipient-autocomplete').val() && self.hasresults) {
            self.rootel.addClass('showing-results');
            self.rootel.find('.recipient-results').addClass('active');
        }
    };

    return {
        init: init
    };
});