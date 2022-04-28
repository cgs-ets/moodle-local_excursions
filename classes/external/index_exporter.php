<?php
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
 * Provides {@link local_excursions\external\index_exporter} class.
 *
 * @package   local_excursions
 * @copyright 2021 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_excursions\external;

defined('MOODLE_INTERNAL') || die();

use renderer_base;
use core\external\exporter;

/**
 * Exporter of a single activity
 */
class index_exporter extends exporter {

    /**
    * Return the list of additional properties.
    *
    * Calculated values or properties generated on the fly based on standard properties and related data.
    *
    * @return array
    */
    protected static function define_other_properties() {
        return [
            'useractivities' => [
                'type' => activity_exporter::read_properties_definition(),
                'multiple' => true,
                'optional' => false,
            ],
            'approveractivities' => [
                'type' => activity_exporter::read_properties_definition(),
                'multiple' => true,
                'optional' => false,
            ],
            'accompanyingactivities' => [
                'type' => activity_exporter::read_properties_definition(),
                'multiple' => true,
                'optional' => false,
            ],
            'auditoractivities' => [
                'type' => activity_exporter::read_properties_definition(),
                'multiple' => true,
                'optional' => false,
            ],
            'parentactivities' => [
                'type' => activity_exporter::read_properties_definition(),
                'multiple' => true,
                'optional' => false,
            ],
            'studentactivities' => [
                'type' => activity_exporter::read_properties_definition(),
                'multiple' => true,
                'optional' => false,
            ],
            'primaryactivities' => [
                'type' => activity_exporter::read_properties_definition(),
                'multiple' => true,
                'optional' => false,
            ],
            'senioractivities' => [
                'type' => activity_exporter::read_properties_definition(),
                'multiple' => true,
                'optional' => false,
            ],


            'has_useractivities' => [
                'type' => PARAM_INT,
            ],
            'has_approveractivities' => [
                'type' => PARAM_INT,
            ],
            'has_accompanyingactivities' => [
                'type' => PARAM_INT,
            ],
            'has_auditoractivities' => [
                'type' => PARAM_INT,
            ],
            'has_parentactivities' => [
                'type' => PARAM_INT,
            ],
            'has_studentactivities' => [
                'type' => PARAM_INT,
            ],
            'has_primaryactivities' => [
                'type' => PARAM_INT,
            ],
            'has_senioractivities' => [
                'type' => PARAM_INT,
            ],

            'isselected_useractivities' => [
                'type' => PARAM_INT,
            ],
            'isselected_approveractivities' => [
                'type' => PARAM_INT,
            ],
            'isselected_accompanyingactivities' => [
                'type' => PARAM_INT,
            ],
            'isselected_auditoractivities' => [
                'type' => PARAM_INT,
            ],
            'isselected_parentactivities' => [
                'type' => PARAM_INT,
            ],
            'isselected_studentactivities' => [
                'type' => PARAM_INT,
            ],
            'isselected_primaryactivities' => [
                'type' => PARAM_INT,
            ],
            'isselected_senioractivities' => [
                'type' => PARAM_INT,
            ],

            'indexurl' => [
                'type' => PARAM_RAW,
                'multiple' => false,
                'optional' => false,
            ],
            'activitycreateurl' => [
                'type' => PARAM_RAW,
                'multiple' => false,
                'optional' => false,
            ],
            'isstaff' => [
                'type' => PARAM_BOOL,
                'multiple' => false,
                'optional' => false,
            ],
            'isparent' => [
                'type' => PARAM_BOOL,
                'multiple' => false,
                'optional' => false,
            ],
            'noexcursions' => [
                'type' => PARAM_BOOL,
                'multiple' => false,
                'optional' => false,
            ],
        ];
    }

    /**
    * Returns a list of objects that are related.
    *
    * Data needed to generate "other" properties.
    *
    * @return array
    */
    protected static function define_related() {
        return [
            'useractivities' => 'local_excursions\persistents\activity[]',
            'approveractivities' => 'local_excursions\persistents\activity[]',
            'accompanyingactivities' => 'local_excursions\persistents\activity[]',
            'auditoractivities' => 'local_excursions\persistents\activity[]',
            'parentactivities' => 'local_excursions\persistents\activity[]',
            'studentactivities' => 'local_excursions\persistents\activity[]',
            'primaryactivities' => 'local_excursions\persistents\activity[]',
            'senioractivities' => 'local_excursions\persistents\activity[]',
            'isstaff' => 'bool',
        ];
    }

    /**
     * Get the additional values to inject while exporting.
     *
     * @param renderer_base $output The renderer.
     * @return array Keys are the property names, values are their values.
     */
    protected function get_other_values(renderer_base $output) {

        $relateds = array('minimal' => true);

        $useractivities = array();
        $allactivities = array();
		foreach ($this->related['useractivities'] as $activity) {
			$activityexporter = new activity_exporter($activity, $relateds);
            $exported = $activityexporter->export($output);
			$useractivities[] = $exported;
            $allactivities[$exported->id] = $exported;
		}

        $approveractivities = array();
        foreach ($this->related['approveractivities'] as $activity) {
            if (isset($allactivities[$activity->get('id')])) {
                $approveractivities[] = $allactivities[$activity->get('id')];
            } else {
                $activityexporter = new activity_exporter($activity, $relateds);
                $exported = $activityexporter->export($output);
                $approveractivities[] = $exported;
                $allactivities[$exported->id] = $exported;
            }
        }

        $accompanyingactivities = array();
        foreach ($this->related['accompanyingactivities'] as $activity) {
            if (isset($allactivities[$activity->get('id')])) {
                $accompanyingactivities[] = $allactivities[$activity->get('id')];
            } else {
                $activityexporter = new activity_exporter($activity, $relateds);
                $exported = $activityexporter->export($output);
                $accompanyingactivities[] = $exported;
                $allactivities[$exported->id] = $exported;
            }
        }

        $auditoractivities = array();
        foreach ($this->related['auditoractivities'] as $activity) {
            if (isset($allactivities[$activity->get('id')])) {
                $auditoractivities[] = $allactivities[$activity->get('id')];
            } else {
                $activityexporter = new activity_exporter($activity, $relateds);
                $exported = $activityexporter->export($output);
                $auditoractivities[] = $exported;
                $allactivities[$exported->id] = $exported;
            }
        }

        $parentactivities = array();
        foreach ($this->related['parentactivities'] as $activity) {
            if (isset($allactivities[$activity->get('id')])) {
                $parentactivities[] = $allactivities[$activity->get('id')];
            } else {
                $activityexporter = new activity_exporter($activity, $relateds);
                $exported = $activityexporter->export($output);
                $parentactivities[] = $exported;
                $allactivities[$exported->id] = $exported;
            }
        }

        $studentactivities = array();
        foreach ($this->related['studentactivities'] as $activity) {
            if (isset($allactivities[$activity->get('id')])) {
                $studentactivities[] = $allactivities[$activity->get('id')];
            } else {
                $activityexporter = new activity_exporter($activity, $relateds);
                $exported = $activityexporter->export($output);
                $studentactivities[] = $exported;
                $allactivities[$exported->id] = $exported;
            }
        }

        $primaryactivities = array();
        foreach ($this->related['primaryactivities'] as $activity) {
            if (isset($allactivities[$activity->get('id')])) {
                $primaryactivities[] = $allactivities[$activity->get('id')];
            } else {
                $activityexporter = new activity_exporter($activity, $relateds);
                $exported = $activityexporter->export($output);
                $primaryactivities[] = $exported;
                $allactivities[$exported->id] = $exported;
            }
        }

        $senioractivities = array();
        foreach ($this->related['senioractivities'] as $activity) {
            if (isset($allactivities[$activity->get('id')])) {
                $senioractivities[] = $allactivities[$activity->get('id')];
            } else {
                $activityexporter = new activity_exporter($activity, $relateds);
                $exported = $activityexporter->export($output);
                $senioractivities[] = $exported;
                $allactivities[$exported->id] = $exported;
            }
        }

        $indexurl = new \moodle_url('/local/excursions/index.php', []);

        $activitycreateurl = new \moodle_url('/local/excursions/activity.php', array(
            'create' => 1,
        ));

        $noexcursions = false;
        if ( empty($allactivities) ) {
            $noexcursions = true;
        }

        unset($allactivities);

        $has_parentactivities = count($parentactivities);
        $has_studentactivities = count($studentactivities);
        $has_useractivities = count($useractivities);
        $has_accompanyingactivities = count($accompanyingactivities);
        $has_approveractivities = count($approveractivities);
        $has_auditoractivities = count($auditoractivities);
        $has_primaryactivities = count($primaryactivities);
        $has_senioractivities = count($senioractivities);

        $isselected_parentactivities = 0;
        $isselected_studentactivities = 0;
        $isselected_useractivities = 0;
        $isselected_accompanyingactivities = 0;
        $isselected_approveractivities = 0;
        $isselected_auditoractivities = 0;
        $isselected_primaryactivities = 0;
        $isselected_senioractivities = 0;
        if ($has_parentactivities) {
            $isselected_parentactivities = 1;
        } else if ($has_studentactivities) {
            $isselected_studentactivities = 1;
        } else if ($has_useractivities) {
            $isselected_useractivities = 1;
        } else if ($has_accompanyingactivities) {
            $isselected_accompanyingactivities = 1;
        } else if ($has_approveractivities) {
            $isselected_approveractivities = 1;
        } else if ($has_auditoractivities) {
            $isselected_auditoractivities = 1;
        } else if ($has_primaryactivities) {
            $isselected_primaryactivities = 1;
        } else if ($has_senioractivities) {
            $isselected_senioractivities = 1;
        }

        return array(
            'useractivities' => $useractivities,
            'approveractivities' => $approveractivities,
            'accompanyingactivities' => $accompanyingactivities,
            'auditoractivities' => $auditoractivities,
            'parentactivities' => $parentactivities,
            'studentactivities' => $studentactivities,
            'primaryactivities' => $primaryactivities,
            'senioractivities' => $senioractivities,

            'has_parentactivities' => $has_parentactivities,
            'has_studentactivities' => $has_studentactivities,
            'has_useractivities' => $has_useractivities,
            'has_accompanyingactivities' => $has_accompanyingactivities,
            'has_approveractivities' => $has_approveractivities,
            'has_auditoractivities' => $has_auditoractivities,
            'has_primaryactivities' => $has_primaryactivities,
            'has_senioractivities' => $has_senioractivities,

            'isselected_parentactivities' => $isselected_parentactivities,
            'isselected_studentactivities' => $isselected_studentactivities,
            'isselected_useractivities' => $isselected_useractivities,
            'isselected_accompanyingactivities' => $isselected_accompanyingactivities,
            'isselected_approveractivities' => $isselected_approveractivities,
            'isselected_auditoractivities' => $isselected_auditoractivities,
            'isselected_primaryactivities' => $isselected_primaryactivities,
            'isselected_senioractivities' => $isselected_senioractivities,

            'activitycreateurl' => $activitycreateurl->out(false),
            'indexurl' => $indexurl->out(false),
            'isstaff' => $this->related['isstaff'],
            'isparent' => count($parentactivities),
            'noexcursions' => $noexcursions,
        );
    }

}
