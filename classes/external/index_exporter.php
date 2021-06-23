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
                $approveractivities[] = $allactivities[$activity->get('id')];
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
                $approveractivities[] = $allactivities[$activity->get('id')];
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
                $approveractivities[] = $allactivities[$activity->get('id')];
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
                $approveractivities[] = $allactivities[$activity->get('id')];
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
                $approveractivities[] = $allactivities[$activity->get('id')];
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
                $approveractivities[] = $allactivities[$activity->get('id')];
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

        return array(
            'useractivities' => $useractivities,
            'approveractivities' => $approveractivities,
            'accompanyingactivities' => $accompanyingactivities,
            'auditoractivities' => $auditoractivities,
            'parentactivities' => $parentactivities,
            'studentactivities' => $studentactivities,
            'primaryactivities' => $primaryactivities,
            'senioractivities' => $senioractivities,
            'activitycreateurl' => $activitycreateurl->out(false),
            'indexurl' => $indexurl->out(false),
            'isstaff' => $this->related['isstaff'],
            'isparent' => count($parentactivities),
            'noexcursions' => $noexcursions,
        );
    }

}
