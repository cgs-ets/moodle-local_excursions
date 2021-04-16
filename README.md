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