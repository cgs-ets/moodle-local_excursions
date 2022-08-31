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
 * Provides the local_excursions/activity module
 *
 * @package   local_excursions
 * @category  output
 * @copyright 2021 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module local_excursions/ActivityForm
 */
define(['jquery', 'local_excursions/recipientselector', 'core/log', 'core/templates', 'core/ajax', 'core/str', 'core/modal_factory', 'core/modal_events', ], 
    function($, RecipientSelector, Log, Templates, Ajax, Str, ModalFactory, ModalEvents) {    
    'use strict';

    /**
     * Initializes the ActivityForm component.
     */
    function init() {
        Log.debug('local_excursions/activity: initializing');

        var rootel = $('#page-local-excursions-activity');

        if (!rootel.length) {
            Log.error('local_excursions/activities: #page-local-excursions-activity not found!');
            return;
        }

        var activityform = new ActivityForm(rootel);
        activityform.main();
    }

    /**
     * The constructor
     *
     * @constructor
     * @param {jQuery} rootel
     */
    function ActivityForm(rootel) {
        var self = this;
        self.rootel = rootel;
        self.form = self.rootel.find('form[data-form="excursions-activity"]');
        self.addstudentsinit = false;
        self.studentlistwrap = self.rootel.find('.student-list-wrap');
        self.studentlist = new Array();
    }

    /**
     * Run the Audience Selector.
     *
     */
   ActivityForm.prototype.main = function () {
        var self = this;
        
        // Render existing student list.
        self.renderStudentsFromJSON();

        // Initialise staff in charge.
        self.staffincharge = RecipientSelector.init('staffincharge', 'staff', 0);
        
        // Initialise planning staff.
        self.planningstaff = RecipientSelector.init('planningstaff', 'staff', 1);

        // Initialise accompanying staff.
        self.accompanyingstaff = RecipientSelector.init('accompanyingstaff', 'staff', 1);

        // Auto-save when leaving the name field. Just to capture the activity name.
        self.rootel.on('blur', 'input[name="activityname"]', function(e) {
            var input = $(this);
            if (input.val().length) {
                self.autoSave();
            }
        });

        // Activity campus change - Primary / senior
        self.rootel.on('change', 'input[name="campus"]', function(e) {
            var value = self.rootel.find('input[name="campus"]:checked' ).val();
            if (value) {
                self.rootel.removeClass(function (index, className) {
                    return (className.match (/(^|\s)activitycampus-\S+/g) || []).join(' ');
                });
                self.rootel.addClass('activitycampus-' + value);
            }
        });

        // Activity type change - excursion / incursion
        self.rootel.on('change', 'input[name="activitytype"]', function(e) {
            var value = self.rootel.find('input[name="activitytype"]:checked' ).val();
            if (value) {
                self.rootel.removeClass(function (index, className) {
                    return (className.match (/(^|\s)activitytype-\S+/g) || []).join(' ');
                });
                self.rootel.addClass('activitytype-' + value);
            }
        });
        self.rootel.find('input[name="campus"]').change();
        self.rootel.find('input[name="activitytype"]').change();

        // Do I need parent permission.
        self.rootel.on('click', '#enablepermissionshelplink', function(e) {
            e.preventDefault();
            self.permissionHelpModal();
        });

        // Add students.
        self.rootel.on('click', '#btn-addstudents', function(e) {
            e.preventDefault();
            self.addStudents();
        });

        // Add student by.
        self.rootel.on('change', 'input[name="addstudentby"]', function(e) {
            var radio = $(this);
            var studentselector = radio.closest('.student-selector');
            studentselector.attr('class', 'student-selector').addClass(radio.val());
        });

        // Selected students changed.
        self.rootel.on('change', 'input.userselect', function(e) {
            self.checkSelected();
        });

        //Delete selected students.
        self.rootel.on('click', '#btn-deletestudents', function(e) {
            e.preventDefault();
            self.deleteStudents();
        });




        // Save draft.
        self.rootel.on('click', 'input[name="savedraft"]', function(e) {
            self.form.find('[name="action"]').val('savedraft');
        });

        // Cancel.
        self.rootel.on('click', 'input[name="cancel"]', function(e) {
            self.form.find('[name="action"]').val('cancel');
        });

        // Delete.
        self.rootel.on('click', 'input[name="delete"]', function(e) {
            self.form.find('[name="action"]').val('delete');
        });

        // Send for review.
        self.rootel.on('click', 'input[name="sendforreview"]', function(e) {
            self.form.find('[name="action"]').val('sendforreview');
        });




        // Post comment.
        self.rootel.on('click', '#btn-postcomment', function(e) {
            e.preventDefault();
            var button = $(this);
            self.postComment(button);
        });

        // Delete comment.
        self.rootel.on('click', '.delete-comment', function(e) {
            e.preventDefault();
            var button = $(this);
            self.deleteComment(button);
        });

        // Approve
        self.rootel.on('change', 'input.approve', function(e) {
            var checkbox = $(this);
            self.submitApproval(checkbox);
        });

        // Skip approval
        self.rootel.on('click', '.approval .action-skip[data-skip="0"]', function(e) {
            e.preventDefault();
            var button = $(this);
            self.skipApproval(button, 1);
        });
        
        // Reenable approval
        self.rootel.on('click', '.approval .action-skip[data-skip="1"]', function(e) {
            e.preventDefault();
            var button = $(this);
            self.skipApproval(button, 0);
        });

        // Notice - Delete previous absences
        self.rootel.on('click', '.notice .action-delete-absences', function(e) {
            var button = $(this);
            self.deletePreviousAbsences(button);
        });

        // Enable permissions
        self.rootel.on('change', 'input[name="enable-permissions"]', function(e) {
            var checkbox = $(this);
            self.enablePermissions(checkbox);
        });

        // Expand prepare message
        self.rootel.on('click', '#btn-preparemessage', function(e) {
            e.preventDefault();
            self.rootel.find('.prepare-message').addClass('active');
            self.rootel.find('#btn-preparemessage').addClass('opened');
        });

        // Send email.
        self.rootel.on('click', '#btn-sendpermissions', function(e) {
            e.preventDefault();
            var button = $(this);
            self.sendPermissions(button);
        });

        // Check all.
        self.rootel.on('click', '#btn-checkall', function(e) {
            e.preventDefault();
            self.checkAllStudents();
        });

        // Uncheck all.
        self.rootel.on('click', '#btn-uncheckall', function(e) {
            e.preventDefault();
            self.uncheckAllStudents();
        });

        // Set the hidden limit input when leaving the limit field.
        self.rootel.on('change', 'input[name="invitetype"]', function(e) {
            var radio = $(this);
            self.rootel.find('.invitetype-message').hide();
            self.rootel.find('.invitetype-message[data-type="' + radio.val() + '"').show();
            self.studentlistwrap.removeClass (function (index, className) {
                return (className.match (/(^|\s)invitetype-\S+/g) || []).join(' ');
            });
            self.studentlistwrap.addClass('invitetype-' + radio.val());
            self.form.find('input[name="hiddeninvitetype"]').val(radio.val());
        });
        self.rootel.on('blur', 'input[name="limit"]', function(e) {
            self.form.find('input[name="hiddenlimit"]').val($(this).val());
        });
        // Set the hidden limit input when leaving the limit field.
        self.rootel.on('blur', 'input[name="limit"]', function(e) {
            self.form.find('input[name="hiddenlimit"]').val($(this).val());
        });
        // Set the hidden dueby input when leaving the due by fields.
        self.rootel.on('blur', "select[name^='timedueby']", function(e) {
            var dueby = self.rootel.find("select[name^='timedueby']").map(function(){return $(this).val();}).get();
            self.form.find('input[name="hiddendueby"]').val(JSON.stringify(dueby));
        });

        // Preview extratext
        self.rootel.on('click', '.btn-showextratext', function(e) {
            e.preventDefault();
            var button = $(this);
            self.previewEmail(button);
        });

        // Preload the modals and templates.
        self.modals = {
            ADDSTUDENTS: null,
            PREVIEWEMAIL: null,
            PERMISSIONSHELP: null,
        };
        self.templates = {
            ADDSTUDENTS: 'local_excursions/activityform_studentselector',
            PREVIEWEMAIL: 'local_excursions/preview_permissions_email',
        };

        var preloads = [];
        preloads.push(self.loadModal('ADDSTUDENTS', '', 'Add', ModalFactory.types.SAVE_CANCEL));
        preloads.push(self.loadModal('PREVIEWEMAIL', 'Email template', '', ModalFactory.types.DEFAULT));
        preloads.push(self.loadModal('PERMISSIONSHELP', 'Do I need parent permission?', '', ModalFactory.types.DEFAULT));
        preloads.push(self.loadTemplate('ADDSTUDENTS'));
        preloads.push(self.loadTemplate('PREVIEWEMAIL'));

        $.when.apply($, preloads).then(function() {
            // Preloads complete.
            self.rootel.removeClass('preloading').addClass('preloads-completed');

            self.loadComments();
        })

    };

    /**
     * Add students
     *
     * @method
     */
    ActivityForm.prototype.renderStudentsFromJSON = function () {
        var self = this;

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

    };


    /**
     * Add students
     *
     * @method
     */
    ActivityForm.prototype.addStudents = function () {
        var self = this;

        if (self.modals.ADDSTUDENTS) {
            // Only init this once.
            if (!self.addstudentsinit) {
                // Get the course and taglists data.
                Ajax.call([{
                    methodname: 'local_excursions_formcontrol',
                    args: { 
                        action: 'get_student_selector_data',
                        data: '',
                    },
                    done: function(json) {
                        var data = JSON.parse(json);
                        // Set the modal content.
                        Templates.render(self.templates.ADDSTUDENTS, {
                                "courses" : data.courses,
                                "groups" : data.groups,
                                "taglists" : data.taglists,
                            })
                            .done(function(html) {
                                self.modals.ADDSTUDENTS.setBody(html);
                                self.studentselector = RecipientSelector.init('studentselector', 'student', 1);
                                self.addstudentsinit = true;
                            })
                            .fail(function(reason) {
                                Log.debug(reason);
                                return "Failed to render student selector."
                            });
                    },
                    fail: function(reason) {
                        Log.debug(reason);
                        return "Failed to load student selector data."
                    }
                }]);

                // Set up the modal cevents.
                self.modals.ADDSTUDENTS.getModal().addClass('modal-xl');
                self.modals.ADDSTUDENTS.getRoot().on(ModalEvents.save, {self: self}, self.handleAddStudents);
            }

            self.modals.ADDSTUDENTS.show();

        }
    };

    ActivityForm.prototype.handleAddStudents = function (event) {
        var self = event.data.self;

        // Immediately set loading.
        self.studentlistwrap.addClass('loading');
        
        // Determine what add type was used.
        var addby = self.rootel.find('input[name="addstudentby"]:checked').val();

        if (addby == 'individual') {
            var input = self.rootel.find('input[name="studentselectorjson"]');
            if (input.val()) {
                var json = JSON.parse(input.val());
                if (json.length) {
                    self.studentlistwrap.addClass('changes-not-saved');
                    for(var i = 0; i < json.length; i++) {
                        self.studentlist.push(json[i].idfield.toString());
                    }
                    self.regenerateStudentList();
                }
                // clear the selections and rerender.
                input.val('');
                self.studentselector.render();
                return;
            }
        }
        
        if (addby == 'course') {
            var select = self.rootel.find('select[name="course"]'); 
            // Get the usernames from the selected course.                   
            Ajax.call([{
                methodname: 'local_excursions_formcontrol',
                args: { 
                    action: 'get_student_usernames_from_courseid',
                    data: select.val(),
                },
                done: function(json) {
                    var usernames = JSON.parse(json);
                    if (usernames.length) {
                        var oldstudentlist = JSON.stringify(self.studentlist.filter(self.onlyUnique));
                        for(var i = 0; i < usernames.length; i++) {
                            self.studentlist.push(usernames[i].toString());
                        }
                        var newstudentlist = JSON.stringify(self.studentlist.filter(self.onlyUnique));
                        if (oldstudentlist != newstudentlist) {
                            self.studentlistwrap.addClass('changes-not-saved');
                        }
                    }
                    self.regenerateStudentList();
                },
                fail: function(reason) {
                    Log.debug(reason);
                }
            }]);
            return;
        }

        if (addby == 'group') {
            var select = self.rootel.find('select[name="group"]'); 
            // Get the usernames from the selected course.                   
            Ajax.call([{
                methodname: 'local_excursions_formcontrol',
                args: { 
                    action: 'get_student_usernames_from_groupid',
                    data: select.val(),
                },
                done: function(json) {
                    var usernames = JSON.parse(json);
                    if (usernames.length) {
                        var oldstudentlist = JSON.stringify(self.studentlist.filter(self.onlyUnique));
                        for(var i = 0; i < usernames.length; i++) {
                            self.studentlist.push(usernames[i].toString());
                        }
                        var newstudentlist = JSON.stringify(self.studentlist.filter(self.onlyUnique));
                        if (oldstudentlist != newstudentlist) {
                            self.studentlistwrap.addClass('changes-not-saved');
                        }
                    }
                    self.regenerateStudentList();
                },
                fail: function(reason) {
                    Log.debug(reason);
                }
            }]);
            return;
        }

        if (addby == 'taglist') {
            var usertaglists = self.rootel.find('select[name="usertaglists"]'); 
            var publictaglists = self.rootel.find('select[name="publictaglists"]'); 

            // Get the usernames from the selected taglists.                   
            Ajax.call([{
                methodname: 'local_excursions_formcontrol',
                args: { 
                    action: 'get_student_usernames_from_taglists',
                    data: JSON.stringify({
                        'user' : usertaglists.val(),
                        'public' : publictaglists.val(),
                    }),
                },
                done: function(json) {
                    var usernames = JSON.parse(json);
                    if (usernames.length) {
                        var oldstudentlist = JSON.stringify(self.studentlist.filter(self.onlyUnique));
                        for(var i = 0; i < usernames.length; i++) {
                            self.studentlist.push(usernames[i].toString());
                        }
                        var newstudentlist = JSON.stringify(self.studentlist.filter(self.onlyUnique));
                        if (oldstudentlist != newstudentlist) {
                            self.studentlistwrap.addClass('changes-not-saved');
                        }
                    }
                    self.regenerateStudentList();
                },
                fail: function(reason) {
                    Log.debug(reason);
                }
            }]);
            
            usertaglists.val('');
            publictaglists.val('');
            return;
        }

        self.studentlistwrap.removeClass('loading');
    };


    /**
     * Add users to studentlist
     *
     */
    ActivityForm.prototype.regenerateStudentList = function () {
        var self = this;
        var body = self.rootel.find('.student-list tbody');
        var activityid = self.form.find('input[name="edit"]').val();

        self.studentlist = self.studentlist.filter(self.onlyUnique);
        self.form.find('input[name="studentlistjson"]').val(JSON.stringify(self.studentlist));

        // Convert to student list rows html via service.
        Ajax.call([{
            methodname: 'local_excursions_formcontrol',
            args: { 
                action: 'regenerate_student_list',
                data: JSON.stringify({
                    'activityid' : activityid,
                    'users' : self.studentlist,
                }),
            },
            done: function(html) {
                body.html(html);
                var medireport = self.form.find('.medical-report');
                if (self.studentlist.length === 0) {
                    self.studentlistwrap.removeClass('has-students');
                    medireport.removeClass('has-students');
                } else {
                    self.studentlistwrap.addClass('has-students');
                    medireport.addClass('has-students');
                }
                self.studentlistwrap.removeClass('loading');

                // Update student count
                var count = self.studentlistwrap.find('tr.student').length;
                self.studentlistwrap.find('.count-students').html(count);
                
                // If an activity does not have an email message history, and system-generated permissions is enabled, check all students and show preview.
                var isapproved = self.rootel.hasClass('activity-status-3');
                var nomessagehistory = (! self.studentlistwrap.hasClass('has-message-history'));
                var permissionsenabled = self.studentlistwrap.hasClass('permissions-enabled');
                var invitetypesystem = self.studentlistwrap.hasClass('invitetype-system');
                if (isapproved && nomessagehistory && permissionsenabled && invitetypesystem) {
                    self.checkAllStudents();
                    self.rootel.find('#btn-preparemessage').click();
                }


                self.checkSelected();
            },
            fail: function(reason) {
                Log.debug(reason);
                self.studentlistwrap.removeClass('loading');
            }
        }]);
    }

    /**
     * Add students
     *
     * @method
     */
    ActivityForm.prototype.deleteStudents = function () {
        var self = this;

        // Immediately set loading.
        self.studentlistwrap.addClass('loading');
        
        // Determine what add type was used.
        self.rootel.find('input.userselect:checked').each(function(index) {
            var username = $(this).val();

            // Remove the user from the array.
            var index = self.studentlist.indexOf(username.toString());
            if (index !== -1) {
                self.studentlist.splice(index, 1);
            }
        });

        self.studentlistwrap.addClass('changes-not-saved');

        // Regenerate the student list.
        self.regenerateStudentList();
    };


    ActivityForm.prototype.checkSelected = function () {
        var self = this;
        
        var selected = self.rootel.find('input.userselect:checked');
        if (selected.length) {
            self.studentlistwrap.addClass('has-selected');
        } else {
            self.studentlistwrap.removeClass('has-selected');
        }
    };

    /**
     * Autosave progress.
     *
     * @method
     */
    ActivityForm.prototype.autoSave = function () {
        var self = this;

        var formjson = self.getFormJSON();

        Ajax.call([{
            methodname: 'local_excursions_formcontrol',
            args: { 
                action: 'autosave',
                data: formjson,
            },
            done: function() {
            },
            fail: function(reason) {
                Log.debug(reason);
            }
        }]);
    };

    /**
     * Generate json from form fields.
     *
     * @method
     */
    ActivityForm.prototype.getFormJSON = function () {
        var self = this;

        var id = self.form.find('input[name="edit"]').val();
        var activityname = self.form.find('input[name="activityname"]').val();

        var formdata = {
            id: id,
            activityname: activityname,
        };

        var formjson = JSON.stringify(formdata);

        return formjson;
    };

    /**
     * Helper used to preload a modal
     *
     * @method loadModal
     * @param {string} modalkey The property of the global modals variable
     * @param {string} title The title of the modal
     * @param {string} title The button text of the modal
     * @return {object} jQuery promise
     */
    ActivityForm.prototype.loadModal = function (modalkey, title, buttontext, type) {
        var self = this;
        return ModalFactory.create({type: type}).then(function(modal) {
            modal.setTitle(title);
            if (buttontext) {
                modal.setSaveButtonText(buttontext);
            }
            self.modals[modalkey] = modal;
            // Preload backgrop.
            modal.getBackdrop();
            modal.getRoot().addClass('modal-' + modalkey);
        });
    }

    /**
     * Helper used to preload a template
     *
     * @method loadModal
     * @param {string} templatekey The property of the global templates variable
     * @return {object} jQuery promise
     */
    ActivityForm.prototype.loadTemplate = function (templatekey) {
        var self = this;
        return Templates.render(self.templates[templatekey], {});
    }

    /**
     * Helper used to return unique array.
     *
     * @method loadModal
     * @param {string} templatekey The property of the global templates variable
     * @return {object} jQuery promise
     */
    ActivityForm.prototype.onlyUnique = function (value, index, self) {
      return self.indexOf(value) === index;
    }

    /**
     * Post comment.
     *
     * @method postComment
     */
    ActivityForm.prototype.postComment = function (button) {
        var self = this;

        var activityid = self.form.find('input[name="edit"]').val();
        var replyform = self.rootel.find('.reply-form');
        var comment = replyform.find('.reply-comment');
        var comments = self.rootel.find('.comments');

        if (comment.val().trim().length == 0) {
            return;
        }

        replyform.addClass('submitting');
        comments.addClass('loading');

        Ajax.call([{
            methodname: 'local_excursions_formcontrol',
            args: { 
                action: 'post_comment',
                data: JSON.stringify({
                    'activityid' : activityid,
                    'comment' : comment.val(),
                }),
            },
            done: function(response) {
                replyform.removeClass('submitting');
                comment.val('');
                self.loadComments();
            },
            fail: function(reason) {
                replyform.removeClass('submitting');
                Log.error('local_excursions/activityform: failed to post comment.');
                Log.debug(reason);
            }
        }]);
    };

    /**
     * Delete comment.
     *
     * @method deleteComment
     */
    ActivityForm.prototype.deleteComment = function (button) {
        var self = this;

        var commentid = button.data('id');

        var comments = self.rootel.find('.comments');
        comments.addClass('loading');

        Ajax.call([{
            methodname: 'local_excursions_formcontrol',
            args: { 
                action: 'delete_comment',
                data: commentid,
            },
            done: function(response) {
                self.loadComments();
            },
            fail: function(reason) {
                Log.error('local_excursions/activityform: failed to delete comment.');
                Log.debug(reason);
            }
        }]);
    };

    /**
     * Load comment.
     *
     * @method loadComment
     */
    ActivityForm.prototype.loadComments = function (button) {
        var self = this;

        var activityid = self.form.find('input[name="edit"]').val();
        var comments = self.rootel.find('.comments');

        comments.addClass('loading');
      
        Ajax.call([{
            methodname: 'local_excursions_formcontrol',
            args: { 
                action: 'get_comments',
                data: activityid,
            },
            done: function(html) {
                comments.removeClass('loading');
                comments.html(html);
            },
            fail: function(reason) {
                comments.removeClass('loading');
                Log.error('local_excursions/activityform: failed to load comments.');
                Log.debug(reason);
            }
        }]);
    };


    /**
     * Submit approve
     *
     * @method postComment
     */
    ActivityForm.prototype.submitApproval = function (checkbox) {
        var self = this;

        var checked = 0;
        if(checkbox.is(':checked')) {
             checked = 1;
        }

        var activityid = self.form.find('input[name="edit"]').val();
        var approvalid = checkbox.data('id');
        var approval = checkbox.closest('.approval');

        approval.addClass('submitting');
      
        Ajax.call([{
            methodname: 'local_excursions_formcontrol',
            args: { 
                action: 'save_approval',
                data: JSON.stringify({
                    'activityid' : activityid,
                    'approvalid' : approvalid,
                    'checked' : checked,
                }),
            },
            done: function(response) {
                var data = JSON.parse(response);
                approval.removeClass('submitting');
                approval.attr('data-status', checked);
                // Update status HTML.
                var status = self.rootel.find('.status');
                status.replaceWith(data.statushtml);
                // Update workflow HTML.
                var workflow = self.rootel.find('.workflow');
                workflow.replaceWith(data.workflowhtml);
                // Update activity status.
                self.rootel.removeClass (function (index, className) {
                    return (className.match (/(^|\s)activity-status-\S+/g) || []).join(' ');
                });
                self.rootel.addClass('activity-status-' + data.status);
            },
            fail: function(reason) {
                approval.removeClass('submitting');
                Log.error('local_excursions/activityform: failed to submit approval.');
                Log.debug(reason);
            }
        }]);

    };

    /**
     * Skip approval
     *
     * @method postComment
     */
     ActivityForm.prototype.skipApproval = function (button, skip) {
        var self = this;

        var activityid = self.form.find('input[name="edit"]').val();
        var approval = button.closest('.approval');
        var approvalid = approval.data('id');

        approval.addClass('submitting');
      
        Ajax.call([{
            methodname: 'local_excursions_formcontrol',
            args: { 
                action: 'skip_approval',
                data: JSON.stringify({
                    'activityid' : activityid,
                    'approvalid' : approvalid,
                    'skip' : skip,
                }),
            },
            done: function(response) {
                var data = JSON.parse(response);
                approval.removeClass('submitting');
                // Update skip status.
                approval.attr('data-skip', skip);
                button.attr('data-skip', skip);
                // Update status HTML.
                var status = self.rootel.find('.status');
                status.replaceWith(data.statushtml);
                // Update workflow HTML.
                var workflow = self.rootel.find('.workflow');
                workflow.replaceWith(data.workflowhtml);
                // Update activity status.
                self.rootel.removeClass (function (index, className) {
                    return (className.match (/(^|\s)activity-status-\S+/g) || []).join(' ');
                });
                self.rootel.addClass('activity-status-' + data.status);
            },
            fail: function(reason) {
                approval.removeClass('submitting');
                Log.error('local_excursions/activityform: failed to submit approval skip.');
                Log.debug(reason);
            }
        }]);
    };

    /**
     * Delete previous absences
     *
     * @method deletePreviousAbsences
     */
     ActivityForm.prototype.deletePreviousAbsences = function (button) {
      var self = this;

      var activityid = self.form.find('input[name="edit"]').val();
      var notice = button.closest('.notice');
      notice.addClass('submitting');
    
      Ajax.call([{
          methodname: 'local_excursions_formcontrol',
          args: { 
            action: 'delete_previous_activities',
            data: activityid,
          },
          done: function(response) {
            notice.html('<td>' + response + '</td>');
            notice.removeClass('submitting');
            notice.addClass('completed');
          },
          fail: function(reason) {
            notice.removeClass('submitting');
            Log.error('local_excursions/activityform: failed to delete previous absences.');
            Log.debug(reason);
          }
      }]);

  };

    /**
     * Enable permissions
     *
     * @method enablePermissions
     */
    ActivityForm.prototype.enablePermissions = function (checkbox) {
        var self = this;

        var checked = 0;
        if(checkbox.is(':checked')) {
            checked = 1;
            self.studentlistwrap.addClass('permissions-enabled');
        } else {
            self.studentlistwrap.removeClass('permissions-enabled');
        }

        var activityid = self.form.find('input[name="edit"]').val();
        Ajax.call([{
            methodname: 'local_excursions_formcontrol',
            args: { 
                action: 'enable_permissions',
                data: JSON.stringify({
                    'activityid' : activityid,
                    'checked' : checked,
                }),
            },
            done: function(response) {
                // silent.
            },
            fail: function(reason) {
                Log.error('local_excursions/activityform: failed to enable permissions.');
                Log.debug(reason);
            }
        }]);

    };

    /**
     * Send permission emails.
     *
     * @method sendPermissions
     */
    ActivityForm.prototype.sendPermissions = function (button) {
        var self = this;

        var users = new Array();
        var selected = self.rootel.find('input.userselect:checked').each(function(index) {
            users.push($(this).val().toString());
        });

        if (!users.length) {
            return;
        }

        var extratext = self.rootel.find('#permissions-extra-text');
        var activityid = self.form.find('input[name="edit"]').val();
        var limit = self.form.find('input[name="limit"]');
        var dueby = self.rootel.find("select[name^='timedueby']").map(function(){return $(this).val();}).get();
        var extratext = self.rootel.find('#permissions-extra-text');

        var preparemessage = self.rootel.find('.prepare-message');
        preparemessage.addClass('submitting');

        Ajax.call([{
            methodname: 'local_excursions_formcontrol',
            args: { 
                action: 'send_permissions',
                data: JSON.stringify({
                    'activityid' : activityid,
                    'limit' : limit.val(),
                    'dueby' : JSON.stringify(dueby),
                    'users' : JSON.stringify(users),
                    'extratext' : extratext.val(),
                }),
            },
            done: function(response) {
                preparemessage.removeClass('submitting').addClass('sent');
                self.reloadMessageHistory();
                setTimeout(function() {
                    self.rootel.find('#btn-preparemessage').removeClass('opened');
                    preparemessage.removeClass('active').removeClass('sent');
                    self.uncheckAllStudents();
                }, 5000);
            },
            fail: function(reason) {
                Log.error('local_excursions/activityform: failed to send permission emails.');
                Log.debug(reason);
            }
        }]);
    };

    /**
     * Uncheck student list.
     *
     * @method uncheckAllStudents
     */
    ActivityForm.prototype.uncheckAllStudents = function () {
        var self = this;
        self.rootel.find('input.userselect').each(function() { 
            this.checked = false;
        });
        self.checkSelected();
    };

    /**
     * Check all students.
     *
     * @method checkAllStudents
     */
    ActivityForm.prototype.checkAllStudents = function () {
        var self = this;
        self.rootel.find('input.userselect').each(function() { 
            this.checked = true;
        });
        self.checkSelected();
    };

    /**
     * Add users to studentlist
     *
     */
    ActivityForm.prototype.reloadMessageHistory = function () {
        var self = this;
        var messagehistory = self.rootel.find('.message-history');
        var activityid = self.form.find('input[name="edit"]').val();

        Ajax.call([{
            methodname: 'local_excursions_formcontrol',
            args: { 
                action: 'get_message_history',
                data: activityid,
            },
            done: function(html) {
                messagehistory.html(html);
                self.studentlistwrap.addClass('has-message-history');
            },
            fail: function(reason) {
                Log.debug(reason);
            }
        }]);
    }

    /**
     * Add students
     *
     * @method
     */
    ActivityForm.prototype.previewEmail = function (button) {
        var self = this;

        var email = button.closest('.emailaction');
        var students = email.find('.c-students').html();
        var extratext = email.find('.c-extratext').html();

        if (self.modals.PREVIEWEMAIL) {
            // Set the modal content.
            Templates.render(self.templates.PREVIEWEMAIL, {
                "info" : '<div style="margin-bottom: 15px;">To the parents of: ' + students + '</div>',
                "emailsubject" : 'Subject: Permissions required for: {activity name}',
                "extratext" : extratext,
                "parentname" : '{parent name will be added automatically}',
                "studentname" : '{student name will be added automatically}',
                "activitydetails" : '{Activity details}',
                "buttonoverride" : '{A button prompting the parent to respond appears here}',
            }).done(function(html) {
                self.modals.PREVIEWEMAIL.setBody(html);
            })
            .fail(function(reason) {
                Log.debug(reason);
                return "Failed to load email preview."
            });
            self.modals.PREVIEWEMAIL.getModal().addClass('modal-xl');
            self.modals.PREVIEWEMAIL.show();
        }
    };

    /**
     * Permission help modal.
     *
     * @method
     */
    ActivityForm.prototype.permissionHelpModal = function () {
        var self = this;

        var body = self.rootel.find('#enablepermissionshelpbody').html();

        if (self.modals.PERMISSIONSHELP) {
            // Set the modal content.
            self.modals.PERMISSIONSHELP.setBody(body);
            self.modals.PERMISSIONSHELP.getModal().addClass('modal-xl');
            self.modals.PERMISSIONSHELP.show();
        }
    };

    return {
        init: init
    };
});