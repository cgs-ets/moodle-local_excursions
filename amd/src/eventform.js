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
 * Provides the local_excursions/eventform
 *
 * @package   local_excursions
 * @category  output
 * @copyright 2023 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module local_excursions/EventForm
 */
define(['jquery', 'local_excursions/recipientselector', 'core/log', 'core/templates', 'core/ajax', 'core/str', 'core/modal_factory', 'core/modal_events', ], 
    function($, RecipientSelector, Log, Templates, Ajax, Str, ModalFactory, ModalEvents) {    
    'use strict';

    /**
     * Initializes the EventForm component.
     */
    function init() {
        Log.debug('local_excursions/event: initializing');

        var rootel = $('#page-local-excursions-event');

        if (!rootel.length) {
            Log.error('#page-local-excursions-event not found!');
            return;
        }

        var eventform = new EventForm(rootel);
        eventform.main();
    }

    /**
     * The constructor
     *
     * @constructor
     * @param {jQuery} rootel
     */
    function EventForm(rootel) {
        var self = this;
        self.rootel = rootel;
        self.form = self.rootel.find('form[data-form="excursions-event"]');
        self.areastree = null;
        self.categoriestree = null;

        ModalFactory.create({type: ModalFactory.types.SAVE_CANCEL}).then(function(modal) {
          modal.setTitle('Conflicts found');
          modal.setSaveButtonText('Submit anyway');
          self.modal = modal;
          self.modal.getModal().addClass('modal-xl');
          self.modal.getRoot().on(ModalEvents.save, function(e) {
            self.submitForm()
          });
        });

        window.addEventListener('beforeunload', function (event) {
          event.stopImmediatePropagation();
        });
    }

    /**
     * Run the Audience Selector.
     *
     */
   EventForm.prototype.main = function () {
        var self = this;
        
        // Render existing areas selection.
        self.renderAreasFromJSON();

        // Initialise owner.
        self.owner = RecipientSelector.init('owner', 'staff', 0);

        // Set up categories tree.
        if(typeof Tree != 'undefined' && typeof calcategories != 'undefined') {
          self.categoriestree = new Tree('.categoriescontainer', {
            data: calcategories,
            closeDepth: 1,
            onChange: function () {
              var categories = $('input[name="categories"]');
              categories.val(JSON.stringify(this.values));
            }
          });
        }

        // Set up areas tree.
        if(typeof Tree != 'undefined' && typeof eventareas != 'undefined') {
          self.areastree = new Tree('.areascontainer', {
            data: eventareas,
            closeDepth: 3,
            onChange: function () {
              var areasJSON = $('input[name="areasjson"]');
              areasJSON.val(JSON.stringify(this.values));
              console.log(this.values)
            }
          });
        }

        // Cancel.
        self.rootel.on('click', 'input[name="cancel"]', function(e) {
            self.form.find('[name="action"]').val('cancel');
        });

        // Delete.
        self.rootel.on('click', 'input[name="delete"]', function(e) {
            self.form.find('[name="action"]').val('delete');
        });

        self.rootel.on('click', '#id_submitbutton', function(e) {
          e.preventDefault()

          var timestart = $('[name="timestart[year]"]').val() + '-' +
          $('[name="timestart[month]"]').val().padStart(2, '0') + '-' +
          $('[name="timestart[day]"]').val().padStart(2, '0') + ' ' +
          $('[name="timestart[hour]"]').val().padStart(2, '0') + ':' +
          $('[name="timestart[minute]"]').val().padStart(2, '0');

          var timeend = $('[name="timeend[year]"]').val() + '-' +
          $('[name="timeend[month]"]').val().padStart(2, '0') + '-' +
          $('[name="timeend[day]"]').val().padStart(2, '0') + ' ' +
          $('[name="timeend[hour]"]').val().padStart(2, '0') + ':' +
          $('[name="timeend[minute]"]').val().padStart(2, '0');

          Ajax.call([{
            methodname: 'local_excursions_formcontrol',
            args: { 
                action: 'check_conflicts',
                data: JSON.stringify({
                  'timestart' : timestart,
                  'timeend' : timeend,
                }),
            },
            done: function(response) {
              let data = JSON.parse(response);
              console.log(data)
              self.form.addClass('conflicts-checked');
              if (data.hasConflicts) {
                self.modal.setBody(data.html);
                self.modal.show()
              } else {
                self.submitForm()
              }
            },
            fail: function(reason) {
              self.form.addClass('conflicts-checked');
              Log.error('local_excursions/eventform: failed to check conflicts.');
              Log.debug(reason);
              self.submitForm()
            }
          }]);
        })

    };

    EventForm.prototype.submitForm = function () {
      var self = this;
      self.modal.hide()
      self.form.submit()
    }

    /**
     * Add students
     *
     * @method
     */
    EventForm.prototype.renderAreasFromJSON = function () {
        var self = this;
/*
        var input = self.form.find('input[name="studentlistjson"]').first();
        var json = input.val();
        var students = [];
        if (json) {
            students = JSON.parse(json);
        }

        self.studentlist = new Array();
        for(var i = 0; i < students.length; i++) {
            self.studentlist.push(students[i]);
        }

        if (self.studentlist.length) {
            self.studentlistwrap.addClass('loading');
            self.regenerateStudentList();
        }
*/
    };


    return {
        init: init
    };
});