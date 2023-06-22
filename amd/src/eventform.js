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
        self.eventid = self.form.data('eventid');
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

    
    EventForm.prototype.convertFieldsToDate = function (input) {
      return $('[name="'+input+'[year]"]').val() + '-' +
      $('[name="'+input+'[month]"]').val().padStart(2, '0') + '-' +
      $('[name="'+input+'[day]"]').val().padStart(2, '0') + ' ' +
      $('[name="'+input+'[hour]"]').val().padStart(2, '0') + ':' +
      $('[name="'+input+'[minute]"]').val().padStart(2, '0');
    }


    /**
     * Run the Audience Selector.
     *
     */
    EventForm.prototype.main = function () {
      var self = this;

      // Initialise owner.
      self.owner = RecipientSelector.init('owner', 'staff', 0);

      // Set up categories tree.
      if(typeof Tree != 'undefined' && typeof calcategories != 'undefined') {
        var categoriesjson = $('input[name="categoriesjson"]').first();
        var categoriesval = categoriesjson.val()
        var values = categoriesval ? JSON.parse(categoriesval) : [];
        self.categoriestree = new Tree('.categoriescontainer', {
          data: calcategories,
          closeDepth: 1,
          loaded: function() {
            this.values = values;
          },
          onChange: function () {
            categoriesjson.val(JSON.stringify(this.values));
          }
        });
      }

      // Set up areas tree.
      if(typeof Tree != 'undefined' && typeof eventareas != 'undefined') {
        var areasjson = $('input[name="areasjson"]').first();
        var areasval = areasjson.val()
        var values = areasval ? JSON.parse(areasval) : [];
        self.areastree = new Tree('.areascontainer', {
          data: eventareas,
          closeDepth: 3,
          loaded: function() {
            this.values = values;
          },
          onChange: function () {
            areasjson.val(JSON.stringify(this.values));
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

        var timestart = self.convertFieldsToDate('timestart');
        var timeend = self.convertFieldsToDate('timeend');

        var recurring = $('input[name="recurring"]:checked').val()
        if (recurring) {
          var recurringpattern = $('input[name="recurringpattern"]:checked').val()
          var recurringdailypattern = $('input[name="recurringdailypattern"]:checked').val()
          var recuruntil =self.convertFieldsToDate('recuruntil');
        }

        Ajax.call([{
          methodname: 'local_excursions_formcontrol',
          args: { 
              action: 'check_conflicts',
              data: JSON.stringify({
                'timestart' : timestart,
                'timeend' : timeend,
                'eventid' : self.eventid,
                'recurringsettings' : recurring ? {
                  recurring: recurring, 
                  recurringpattern: recurringpattern, 
                  recurringdailypattern: recurringdailypattern, 
                  recuruntil: recuruntil
                } : null,
              }),
          },
          done: function(response) {
            let data = JSON.parse(response);
            console.log(data)
            self.form.addClass('conflicts-checked');
            if (data.conflicts.length) {
              let html = '<div class="conflicts-wrap"><div class="alert alert-warning"><strong>Review the conflicts below and consider whether your event needs to be moved before you continue.</strong></div>';
              html += data.html;
              html += '</div>';
              self.modal.setBody(html);
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

      self.setupCheckRecurring();
  

    }


    EventForm.prototype.checkRecurring = function () {
      var self = this;
      $('#calculated-dates').html('')

      let editseries = $('input[name="editseries"]:checked').val()
      if (editseries == 'event') {
        return;
      }

      let recurring = $('input[name="recurring"]:checked').val()
      if (!recurring) {
        return
      }
      let timestart = self.convertFieldsToDate('timestart');
      let timeend = self.convertFieldsToDate('timeend');
      let recurringpattern = $('input[name="recurringpattern"]:checked').val()
      let recurringdailypattern = $('input[name="recurringdailypattern"]:checked').val()
      let recuruntil =self.convertFieldsToDate('recuruntil');
      
      Ajax.call([{
        methodname: 'local_excursions_formcontrol',
        args: { 
          action: 'expand_dates',
          data: JSON.stringify({
            recurring: {
              recurringdailypattern: recurringdailypattern,
              recurringpattern: recurringpattern,
              recuruntil: recuruntil,
            },
            timestart: timestart,
            timeend: timeend,
          })
        },
        done: function (response) {
          let data = JSON.parse(response);
          if (data.datesReadable === undefined) {
            return;
          }
          if (data.datesReadable.length) {
            let dates = data.datesReadable.map(date => {
              return '<li>' + date.start + ' - ' + date.end + '</li>'
            })
            $('#calculated-dates').html('The following occurrences will be created: <br><ul>' + dates.join(' ') + '</ul>')
          }
        }
      }]);
    }


    EventForm.prototype.submitForm = function () {
      var self = this;

      window.addEventListener('beforeunload', function (event) {
        event.stopImmediatePropagation();
      });

      self.modal.hide()
      self.form.submit()
    }


    EventForm.prototype.setupCheckRecurring = function () {
      var self = this;
      $('input[name="editseries"]').change(function(){
        self.checkRecurring()
      })
      $('input[name="recurring"]').change(function(){
        self.checkRecurring()
      })
      $('input[name="recurringpattern"]').change(function(){
        self.checkRecurring()
      })
      $('input[name="recurringdailypattern"]').change(function(){
        self.checkRecurring()
      })
      $('select[name="recuruntil[day]"]').change(function(){
        self.checkRecurring()
      })
      $('select[name="recuruntil[month]"]').change(function(){
        self.checkRecurring()
      })
      $('select[name="recuruntil[year]"]').change(function(){
        self.checkRecurring()
      })
      $('select[name="recuruntil[hour]"]').change(function(){
        self.checkRecurring()
      })
      $('select[name="recuruntil[minute]"]').change(function(){
        self.checkRecurring()
      })
      
      $('select[name="timestart[day]"]').change(function(){
        self.checkRecurring()
      })
      $('select[name="timestart[month]"]').change(function(){
        self.checkRecurring()
      })
      $('select[name="timestart[year]"]').change(function(){
        self.checkRecurring()
      })
      $('select[name="timestart[hour]"]').change(function(){
        self.checkRecurring()
      })
      $('select[name="timestart[minute]"]').change(function(){
        self.checkRecurring()
      })

      
      
      $('select[name="timeend[day]"]').change(function(){
        self.checkRecurring()
      })
      $('select[name="timeend[month]"]').change(function(){
        self.checkRecurring()
      })
      $('select[name="timeend[year]"]').change(function(){
        self.checkRecurring()
      })
      $('select[name="timeend[hour]"]').change(function(){
        self.checkRecurring()
      })
      $('select[name="timeend[minute]"]').change(function(){
        self.checkRecurring()
      })
    };

    return {
        init: init
    };
});