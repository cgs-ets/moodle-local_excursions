<?php

namespace local_excursions;

class local_excursions_config {
	const WORKFLOW = array(
		'senior_ra' => array(
	    	'name' => 'RA Approval',
	    	'invalidated_on_edit' => array (),
	    	'approvers' => array(
		        '112233' => array(
		            'username' => '112233',
		            'contacts' => null,
		        ),
		        '222222' => array(
		            'username' => '222222',
		            'contacts' => array(
		            	'another.email@cgs.act.edu.au',
		            ),
		        ),
		    ),
		    'prerequisites' => null
	    ),
	    'senior_admin' => array(
	    	'name' => 'Admin Approval',
	    	'invalidated_on_edit' => array (
	    		'timestart',
	    		'timeend',
	    	),
	    	'approvers' => array(
		        '111444' => array(
		            'username' => '111444',
		            'contacts' => null,
                    'notifications' => array('none'),
		        ),
		        '665665' => array(
		            'username' => '665665', 
		            'contacts' => null,
                    'notifications' => array('none'),
		        ),
		    ),
		    'prerequisites' => null,
			'selectable' => true,
	    ),
	    'senior_hoss' => array(
	    	'name' => 'Final Approval',
	    	'invalidated_on_edit' => array (
	    		'timestart',
	    		'timeend',
	    	),
	    	'approvers' => array(
		        '1000' => array(
		            'username' => '1000',
		            'contacts' => null,
                    'notifications' => array('none'),
		        ),
		        '2000' => array(
		            'username' => '2000', 
		            'contacts' => null,
                    'notifications' => array('none'),
		        ),
		    ),
		    'prerequisites' => array(
		    	'senior_ra',
		    	'senior_admin',
		    ),
			'canskip' => true,
			'selectable' => true,
	    ),


	    'primary_ra' => array(
	    	'name' => 'RA Approval',
	    	'invalidated_on_edit' => array (),
	    	'approvers' => array(
		        '123123' => array(
		            'username' => '123123', 
		            'contacts' => null,
		        ),
		        '345345' => array(
		            'username' => '345345',
		            'contacts' => array( // Contact multiple people.
		            	'person1@cgs.act.edu.au',
		            	'person2@cgs.act.edu.au',
		            ),
		        ),
		    ),
		    'prerequisites' => null,
			'canskip' => true,
	    ),
	    'primary_admin' => array(
	    	'name' => 'Admin Approval',
	    	'invalidated_on_edit' => array (
	    		'timestart',
	    		'timeend',
	    	),
	    	'approvers' => array(
		        '123123' => array(
		            'username' => '123123',
		            'contacts' => null,
		        ),
		        '234234' => array(
		            'username' => '234234', 
		            'contacts' => null,
		        ),
		    ),
		    'prerequisites' => null,
	    ),
	    'primary_hops' => array(
	    	'name' => 'HoPS Approval',
	    	'invalidated_on_edit' => array (
	    		'timestart',
	    		'timeend',
	    	),
	    	'approvers' => array(
		        '321321' => array(
		            'username' => '321321',
		            'contacts' => array(
		            	'postion@cgs.act.edu.au',
		            ),
		        ),
		    ),
		    'prerequisites' => array(
		    	'primary_admin',
		    ),
			'canskip' => true,
	    ),

        'whole_admin' => array(
	    	'name' => 'Admin Approval',
	    	'invalidated_on_edit' => array (
	    		'timestart',
	    		'timeend',
	    	),
	    	'approvers' => array(
		        '123123' => array(
		            'username' => '123123',
		            'contacts' => null,
		        ),
		        '234234' => array(
		            'username' => '234234', 
		            'contacts' => null,
		        ),
		    ),
		    'prerequisites' => null,
	    ),
	    'whole_final' => array(
	    	'name' => 'Final Approval',
	    	'invalidated_on_edit' => array (
	    		'timestart',
	    		'timeend',
	    	),
	    	'approvers' => array(
		        '888777' => array(
		            'username' => '888777',
		            'contacts' => array(
		            	'user@cgs.act.edu.au',
		            ),
		        ),
		    ),
		    'prerequisites' => array(
		    	'whole_admin',
		    ),
			'canskip' => false,
	    ),

	);
}