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
        self.submitting = false;

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

      // Assessment
      self.rootel.on('change', 'input[name="assessment"]', function(e) {
        var checkbox = $(this);
        if(checkbox.is(':checked')) {
          self.rootel.find('.entry-type-heading').hide();
          self.rootel.find('#fgroup_id_entrytype').hide();
          var $radios = $('input:radio[name=entrytype]');
          $radios.filter('[value=event]').prop('checked', true);
        } else {
          self.rootel.find('.entry-type-heading').show();
          self.rootel.find('#fgroup_id_entrytype').show();
        }
      });

      self.watchCategories();
      self.categoryChanged();

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

        if (self.submitting) {
          // Do not submit form multiple times as it is already submitting.
          e.preventDefault()
          return;
        }
        
        if (self.conflictCheckDone) {
          self.submitting = true;
          // Allow form submission.
          return;
        }

        // Do conflick check instead of default form submit.
        e.preventDefault()
        var timestart = self.convertFieldsToDate('timestart');
        var timeend = self.convertFieldsToDate('timeend');

        console.log("Checking conflicts.")

        Ajax.call([{
          methodname: 'local_excursions_formcontrol',
          args: { 
              action: 'check_conflicts',
              data: JSON.stringify({
                'timestart' : timestart,
                'timeend' : timeend,
                'eventid' : self.eventid,
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


    EventForm.prototype.submitForm = function () {
      var self = this;

      self.modal.hide()
      self.conflictCheckDone = true;
      $('#id_submitbutton').click()
    }

    EventForm.prototype.watchCategories = function () {
      var self = this;
      $('input[name="categories"]').change(function(e){
        var checkbox = this;
        self.categoryChanged(checkbox);
      })
    }

    EventForm.prototype.categoryChanged = function (checkbox)  {
      var self = this;

      // If checked, make sure parent is select too - BUT NOT FOR PS.
      if (checkbox) {
        if (checkbox.checked) {
          var first = $(checkbox).parent().children(":first")
          if (!first.checked && first.val() != "Primary School") {
            first.prop( "checked", true );
          }
        } else {
          // If any children are still checked, make sure parent is selected too.
          var checkedchildren = $(checkbox).parent().children(":not(:first):checked")
          if (checkedchildren.length) {
            var first = $(checkbox).parent().children(":first")
            if (!first.checked && first.val() != "Primary School") {
              first.prop( "checked", true );
            }
          }
        }
      }

      // Generate the categories JSON.
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

      // If Website or Alumni is selected, tick public.
      var hasExternal = 
        selected.includes('Whole School/Website External') || selected.includes('Whole School/Alumni Website') ||
        selected.includes('Primary School/Website External') || selected.includes('Primary School/Alumni Website') ||
        selected.includes('Senior School/Website External') || selected.includes('Senior School/Alumni Website')
      if (hasExternal) {
        var checkbox = $('input:checkbox[name=displaypublic]');
        checkbox.prop('checked', true);
      }

      // If CGS Board is selected, hide public option.
      var hasBoard = selected.includes('Whole School/CGS Board')
      if (!selected.length || hasBoard) {
        self.rootel.find('#id_displaypublic').closest('.form-group').hide();
      } else {
        self.rootel.find('#id_displaypublic').closest('.form-group').show();
      }

      // If more than one category selected, fill and show the colouring category select box.
      console.log(selected.length)
      if (selected.length > 1) {
        var select = self.rootel.find('#fitem_id_colourselect select');
        select.empty();
        for (var i = 0; i < selected.length; i++){
          var opt = document.createElement('option');
          opt.value = opt.innerHTML = selected[i];
          select.append(opt);
        }
        self.rootel.find('#fitem_id_colourselect').show();
        // Reselect current colour.
        select.val(select.data('selected'));
      } else {
        self.rootel.find('#fitem_id_colourselect').hide();
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
      
      
    };

    return {
        init: init
    };
});