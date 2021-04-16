<?php

namespace local_excursions;

class local_excursions_config {

	const WORKFLOW = array(
	    'senior_admin' => array(
	    	'name' => 'Admin Approval',
	    	'invalidated_on_edit' => array (
	    		'timestart',
	    		'timeend',
	    	),
	    	'approvers' => array(
		        array(
		            'username' => 'admin',
		            'contacts' => null, // Default email in Moodle used.
		        ),
		        array(
		            'username' => 'admin',
		            'contacts' => array(
		            	'admin.email@gmail.com',
		            ),
		        ),
		    ),
		    'prerequisites' => null
	    ),
	    'senior_hoss' => array(
	    	'name' => 'HoSS Approval',
	    	'invalidated_on_edit' => array (
	    		'location',
	    		'timestart',
	    		'timeend',
	    		'riskassessment',
	    	),
	    	'approvers' => array(
		        array(
		            'username' => 'admin', 
		            'contacts' => null,
		        ),
		    ),
		    'prerequisites' => array(
		    	'senior_admin',
		    ),
	    ),
	    'primary_admin' => array(
	    	'name' => 'Admin Approval',
	    	'invalidated_on_edit' => array (
	    		'location',
	    		'timestart',
	    		'timeend',
	    		'riskassessment',
	    	),
	    	'approvers' => array(
		        array(
		            'username' => 'admin',
		            'contacts' => null,
		        ),
		    ),
		    'prerequisites' => null,
	    ),
	    'primary_hops' => array(
	    	'name' => 'HoPS Approval',
	    	'invalidated_on_edit' => array (
	    		'location',
	    		'timestart',
	    		'timeend',
	    		'riskassessment',
	    	),
	    	'approvers' => array(
		        array(
		            'username' => 'admin',
		            'contacts' => null,
		        ),
		        array(
		            'username' => 'admin',
		            'contacts' => array(
		            	'admin.email@gmail.com',
		            ),
		        ),
		    ),
		    'prerequisites' => array(
		    	'primary_admin',
		    ),
	    ),
	);
	
}
