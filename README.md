# moodle-local_excursions

Activity planning system for CGS.

Author
--------
2021 Michael Vangelovski<br/>
<https://github.com/michaelvangelovski><br/>
<http://michaelvangelovski.com><br/>

### Workflow configuration
The workflow is automatically determined based on the campus roles (custom profile field) of the participating students. There are two workflow paths: Primary School (Admin Approval, HoPS Approval) and Senior School (Admin Approval, HoSS Approval). Configuration these workflows is possible via a config.php file inside the local_excursions folder. This is an environment-specific file that must be created manually (it does not come with the git repo). A sample config file (config.sample.php) is provided for reference. The possible configurations for each step of the workflow step are as follows:
 - name → The display name for the workflow step.
 - invalidated_on_edit → Fields in the activity planning form that cause the workflow step to be reset if they are edited after the step has been actioned.
 - approvers → Users that can action this workflow step. The username of the user must be provided. Contacts is an optional array of email addresses to notify instead of the user's email address.
 - prerequisites → Workflow steps that must be actioned before this workflow step can be actioned.

### Completing the form

#### Essential details
Activity name, location, times and cost will automatically be included in permission notes. Approval will be cancelled and need to be renewed if key details of the activity change (see configuration notes above).
![](/screenshots/local_excursions_general_details.png?raw=true)

#### People
Update the Student list with any changes: students can be added individually, by course, group or via a Synergetic taglist. Participating students will automatically be marked in class rolls as expected absences a day before the event takes place.
![](/screenshots/local_excursions_people.png?raw=true)

#### Documents
The risk assessment form, if one is required, must be completed using the template and attached to the Activity Planning form.
![](/screenshots/local_excursions_documents.png?raw=true)

#### Notifications
Any change in the status or details of the activity will be notified to the organising teacher and accompanying teachers by email linked to the form.

on administrative approval | when the Head of School has approved the event | when details are changed | when staff comment on the application
--- | --- | --- | ---
![](/screenshots/local_excursions_notifications_adminapp.png?raw=true) | ![](/screenshots/local_excursions_notifications_headapp.png?raw=true) | ![](/screenshots/local_excursions_notifications_changes.png?raw=true) | ![](/screenshots/local_excursions_notifications_comment.png?raw=true)
			
#### Planning conversations
Respond in the message panel on the right of the Activity Planning form to ensure that correspondence is kept together and visible to all staff involved.
See [Managing permissions](https://screencast-o-matic.com/watch/crfihZVnjFJ) (video).

#### Parental Permission
If “This activity requires parent permission” is ticked, the system will help collect and permanently record permission, and only students with parental permission will be included in the Medical report and expected absences. Parents can change their permission up to the deadline for permission. Either parent of a student can revoke permission.
![](https://github.com/cgs-ets/testtesttest/blob/main/screenshots/local_excursions_permissions.mp4?raw=true)

#### Paperwork
1. The Synergetic medical report is automatically generated based on the participating students. 
2. A chargesheet is automatically generated based on the student list and downloadable for processing.
