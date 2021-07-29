<?php

namespace local_excursions;

class local_excursions_config {

	const WORKFLOW = array(
		// SENIOR ADMIN
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
		    'prerequisites' => null,
	    ),
		// SENIOR HOSS
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
					'notifications' => array(
						'newcomment',
						'activityapproved',
						'activitychanged',
					),
		        ),
		    ),
		    'prerequisites' => array(
		    	'senior_admin',
		    ),
	    ),
		// PRIMARY ADMIN
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
		// PRIMARY HOPS
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
		// INCURSION YEARS 7-10
		'years_7_10' => array(
	    	'name' => 'Approval',
	    	'invalidated_on_edit' => array (
	    		'timestart',
	    		'timeend',
	    	),
	    	'approvers' => array(
		        array(
		            'username' => 'admin',
		            'contacts' => null,
		        ),
		    ),
		    'prerequisites' => null,
	    ),
		// INCURSION YEARS 11 to 12 HSC/VET
		'years_11_12_hsc_vet' => array(
	    	'name' => 'Approval',
	    	'invalidated_on_edit' => array (
	    		'timestart',
	    		'timeend',
	    	),
	    	'approvers' => array(
		        array(
		            'username' => 'admin',
		            'contacts' => null,
		        ),
		    ),
		    'prerequisites' => null,
	    ),
		// INCURSION YEARS 11 to 12 IB
		'years_11_12_ib' => array(
	    	'name' => 'Approval',
	    	'invalidated_on_edit' => array (
	    		'timestart',
	    		'timeend',
	    	),
	    	'approvers' => array(
		        array(
		            'username' => 'admin',
		            'contacts' => null,
		        ),
		    ),
		    'prerequisites' => null,
	    ),

	);
	
}
