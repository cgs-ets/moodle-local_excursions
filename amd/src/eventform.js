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
        self.conflictCheckDone = false;

        ModalFactory.create({type: ModalFactory.types.SAVE_CANCEL}).then(function(modal) {
          modal.setTitle('Conflicts found');
          modal.setButtonText('cancel', 'Review entry');
          modal.setSaveButtonText('Submit anyway');
          self.modal = modal;
          self.modal.getModal().addClass('modal-xl');
          self.modal.getRoot().on(ModalEvents.save, function(e) {
            self.submitForm()
          });
        });

        //window.addEventListener('beforeunload', function (event) {
        //  event.stopImmediatePropagation();
        //});
        window.addEventListener("beforeunload", function(event) {
          console.log("UNLOAD:1");
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

      /*
      // Set up categories tree.
      if(typeof Tree != 'undefined' && typeof calcategories != 'undefined') {
        var categoriesjson = $('input[name="categoriesjson"]').first();
        var categoriesval = categoriesjson.val()
        var values = categoriesval ? JSON.parse(categoriesval) : [];
        self.categoriestree = new Tree('.categoriescontainer', {
          data: calcategories,
          closeDepth: 3,
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
      */

      self.watchCategories();
      self.generateCategoriesJSON();

      var btndelete = document.getElementById('btn-delete');
      btndelete && btndelete.addEventListener('click', e => {
          e.preventDefault()
          let eventid = btndelete.dataset.eventid;
          Ajax.call([{
            methodname: 'local_excursions_formcontrol',
            args: { 
              action: 'delete_event',
              data: eventid,
            },
            done: function (response) {
              document.getElementById('id_cancel').click();
            }
          }]);
      });



      self.rootel.on('click', '#id_submitbutton', function(e) {
        
        if (self.conflictCheckDone) {
          return;
        }

        e.preventDefault()

        var timestart = self.convertFieldsToDate('timestart');
        var timeend = self.convertFieldsToDate('timeend');

        /*var recurring = $('input[name="recurring"]:checked').val()
        if (recurring) {
          var recurringpattern = $('input[name="recurringpattern"]:checked').val()
          var recurringdailypattern = $('input[name="recurringdailypattern"]:checked').val()
          var recuruntil =self.convertFieldsToDate('recuruntil');
        }*/

        Ajax.call([{
          methodname: 'local_excursions_formcontrol',
          args: { 
              action: 'check_conflicts',
              data: JSON.stringify({
                'timestart' : timestart,
                'timeend' : timeend,
                'eventid' : self.eventid,
                /*'recurringsettings' : recurring ? {
                  recurring: recurring, 
                  recurringpattern: recurringpattern, 
                  recurringdailypattern: recurringdailypattern, 
                  recuruntil: recuruntil
                } : null,*/
              }),
          },
          done: function(response) {
            let data = JSON.parse(response);
            console.log(data)
            self.form.addClass('conflicts-checked');
            if (data.conflicts.length) {
              let html = '<div class="conflicts-wrap"><div class="alert alert-warning"><strong>Review the conflicts below and consider whether your event needs to be moved before you continue.</strong></div>';

              html += '<div class="table-heading"><b class="table-heading-label">Event summary</b></div>'

              html += "<table><tr><th>Title</th><th>Dates</th><th>Location</th><th>Areas</th><th>Owner</th></tr>"
              var title = $('input[name="activityname"]').val();
              var location = $('input[name="location"]').val();
              var timestart = '<div>' + $('[name="timestart[hour]"]').val().padStart(2, '0') + ':' +
                              $('[name="timestart[minute]"]').val().padStart(2, '0') + '</div><div><small>' +
                              $('[name="timestart[day]"]').val().padStart(2, '0') + '-' +
                              $('[name="timestart[month]"]').val().padStart(2, '0') + '-' +
                              $('[name="timestart[year]"]').val() + '</small></div>';

              var timeend = '<div>' + $('[name="timeend[hour]"]').val().padStart(2, '0') + ':' +
                            $('[name="timeend[minute]"]').val().padStart(2, '0') + '</div><div><small>' +
                            $('[name="timeend[day]"]').val().padStart(2, '0') + '-' +
                            $('[name="timeend[month]"]').val().padStart(2, '0') + '-' +
                            $('[name="timeend[year]"]').val() + '</small></div>';

              var categoriesjson = $('input[name="categoriesjson"]').first();
              var areasval = categoriesjson.val()
              var areavalues = areasval ? JSON.parse(areasval) : [];
              areavalues = areavalues.map(function(area) {
                return area.split('/')
              })
              areavalues = areavalues.flat(1)
              areavalues = [...new Set(areavalues)]
              var lis = areavalues.map(function(a) { return "<li>" + a + "</li>"})
              var areashtml = "<ul>" + lis.join("") + "</ul>";

              var ownerjson = $('input[name="ownerjson"]').first().val();
              var owner = JSON.parse(ownerjson)
              var ownerhtml = '<div>' + owner.map(function(o) { return '<img class="rounded-circle" height="18" src="' + o.photourl + '"> <span>' + o.fullname + '</span>' } ) + '</div>'

              html += "<tr><td>" + title + "</td>";
              //html += "<td>" + timestart + "</td><td>" + timeend + "</td>";
              html += "<td><div style=\"display:flex;gap:20px;\"><div>" + timestart + "</div><div>" + timeend + "</div></div></td>";
              html += "<td>" + location + "</td>"
              html += "<td>" + areashtml + "</td><td>" + ownerhtml + "</td></tr>"
              html += "</table><br>"
              html += '<div class="table-heading"><b class="table-heading-label">Conflicting events</b></div>'
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

      //self.setupCheckRecurring();
      self.getDayCycle(['timestart', 'timeend']);
      self.initDatesChanged();
      self.initCategorySelect();
    }

    EventForm.prototype.getDayCycle = function (inputs) {
      var self = this;
      for (let i = 0; i < inputs.length; i++) {
        let datetime = self.convertFieldsToDate(inputs[i])
        Ajax.call([{
          methodname: 'local_excursions_formcontrol',
          args: { 
            action: 'get_day_cycle',
            data: JSON.stringify({
              datetime: datetime,
            })
          },
          done: function (response) {
            let data = JSON.parse(response);
            $('#daycycle-' + inputs[i]).html(data)
          }
        }]);
      }
    };


      


    /*EventForm.prototype.checkRecurring = function () {
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
    }*/


    EventForm.prototype.submitForm = function () {
      var self = this;

      //window.addEventListener('beforeunload', function (event) {
      //  event.stopImmediatePropagation();
      //});

      self.modal.hide()
      //self.form.submit()
      self.conflictCheckDone = true;
      $('#id_submitbutton').click()
    }

    EventForm.prototype.watchCategories = function () {
      var self = this;
      $('input[name="categories"]').change(function(e){
        self.generateCategoriesJSON();
      })
      
    }

    EventForm.prototype.generateCategoriesJSON = function ()  {  
      var self = this;
      var checkboxes = document.getElementsByName("categories")
      var selected = [] 
      for (var i = 0; i < checkboxes.length; i++)  
      {
        if (checkboxes[i].checked) {
          selected.push(checkboxes[i].value)
        }
      }
      var categoriesjson = $('input[name="categoriesjson"]')
      categoriesjson.val(JSON.stringify(selected))
      if (selected.length) {
        self.rootel.find('#id_displaypublic').closest('.form-group').show();
      } else {
        self.rootel.find('#id_displaypublic').closest('.form-group').hide();
      }
    } 

    EventForm.prototype.initDatesChanged = function () {
      var self = this;

      $('select[name="timestart[day]"]').change(function(){
        self.getDayCycle(['timestart'])
      })
      $('select[name="timestart[month]"]').change(function(){
        self.getDayCycle(['timestart'])
      })
      $('select[name="timestart[year]"]').change(function(){
        self.getDayCycle(['timestart'])
      })


      $('select[name="timeend[day]"]').change(function(){
        self.getDayCycle(['timeend'])
      })
      $('select[name="timeend[month]"]').change(function(){
        self.getDayCycle(['timeend'])
      })
      $('select[name="timeend[year]"]').change(function(){
        self.getDayCycle(['timeend'])
      })

      //$('.yui3-calendar-day').on('click', function(){
      //  self.getDayCycle(['timestart', 'timeend'])
      //})

    };

    EventForm.prototype.initCategorySelect = function () {
      
      $('.category-group input').change(function(){
        // If checked, make sure parent is select too.
        if (this.checked) {
          var first = $(this).parent().children(":first")
          if (!first.checked) {
            first.prop( "checked", true );
          }
        } else {
          // If any children are still checked, make sure parent is selected too.
          var checkedchildren = $(this).parent().children(":not(:first):checked")
          if (checkedchildren.length) {
            var first = $(this).parent().children(":first")
            if (!first.checked) {
              first.prop( "checked", true );
            }
          }
        }
      })

    };


    /*EventForm.prototype.setupCheckRecurring = function () {
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
    };*/

    return {
        init: init
    };
});