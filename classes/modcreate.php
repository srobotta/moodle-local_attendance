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

namespace local_attendance;

use local_attendance\utils\utils;

/**
 * Interface for creating modules in a course. If you need to crreate specific modules,
 * that are notsupported by this plugin, you may implement this interface in your own class.
 * See README.md for more information.
 *
 * @package     local_attendance
 * @copyright   2025 Stephan Robotta <stephan.robotta@bfh.ch>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class modcreate implements modcreate_interface {
    /**
     * The course where the module will be created.
     */
    protected \stdClass $course;

    /**
     * The current csv row as an associative array.
     * @var array|null
     */
    protected ?array $row = null;

    /**
     * The module info object after creation.
     * @var \stdClass
     */
    protected \stdClass $moduleinfo;

    /**
     * Use an existing course where the activity will be created.
     * Also, you may change course settings here.
     * @param \stdClass $course
     * @return modcreate_interface
     */
    public function useCourse(\stdClass $course): modcreate_interface {
        global $COURSE;
        $this->course = $course;
        // At least completion needs this to be set to the current course where activities are created in.
        $COURSE = $course;
        return $this;
    }

    /**
     * Set the current CSV row data.
     * @param array $row
     * @return modcreate_interface
     */
    public function setRow(array $row): modcreate_interface {
        $this->row = $row;
        return $this;
    }

    /**
     * Create a new activity in the course.
     * The parameter contains the associative array from the import file.
     * @param array $data
     * @return modcreate_interface
     */
    public function create(array $data): modcreate_interface {
        global $CFG;

        // Which module to add? Any inherited class must change the module content to have no underscore so
        // that a base module can be created, before doing any specific actions.
        $modname = $data['module'];
        unset($data['module']);
        // Include the course lib to have the course related functions available.
        require_once($CFG->dirroot . '/course/modlib.php');
        // At which section in the course to add the module? Default is 0 (General).
        // In the csv we start counting sections at 1, so we need to subtract 1.
        $sectionnum = 0;
        if (\array_key_exists('sectionid', $data)) {
            $sectionnum = get_fast_modinfo($this->course)
                ->get_section_info_by_id($data['sectionid'], MUST_EXIST)
                ->sectionnum;
            unset($data['sectionid']);
        } elseif (\array_key_exists('section', $data)) {
            $sectionnum = (int)$data['section'] - 1;
            if ($sectionnum < 0) { // Just in case someone puts section 0 in the csv of the field is empty.
                $sectionnum = 0;
            }
            unset($data['section']);
        }
        if (\array_key_exists('section_pos', $data)) {
            // Section position is also accepted.
            $modules = \course_modinfo::get_array_of_activities($this->course);
            $posCounter = 0;
            foreach ($modules as $mod) {
                if ($mod->section == $sectionnum) {
                    $posCounter++;
                    if ($posCounter >= $data['section_pos']) {
                        $beforeModule = $mod->cm;
                        break;
                    }
                }
            }
            unset($data['section_pos']);
        }

        // Prepare the data for the new module.
        [$mod, $context, $cw, $cm, $modInfoData] = prepare_new_moduleinfo_data($this->course, $modname, $sectionnum);
        $modInfoData->add = $modname;
        $modInfoData->modulename = $modname;
        if (isset($beforeModule)) {
            $modInfoData->beforemod = $beforeModule;
        }
        // Prepare the form in order to get default values for the module, also validation can be done here.
        $modmoodleform = "$CFG->dirroot/mod/{$modname}/mod_form.php";
        if (file_exists($modmoodleform)) {
            require_once($modmoodleform);
        } else {
            throw new \moodle_exception('noformdesc');
        }
        // Load the module form with the data that can be set and apply defaults.
        $mformclassname = "\\mod_{$modname}_mod_form";
        $mform = new $mformclassname($modInfoData, $cw->section, $cm, $this->course);
        $mform->set_data($modInfoData);
        $formData = utils::mergeData($mform, $data);
        // Setup some required fields that are not in the form.
        $formData->modulename = $modname;
        $formData->visible = 1;
        // Thow an error if there is no module name given.
        if (empty($formData->name)) {
            throw new \moodle_exception('ex_modnamemempty', 'local_attendance');
        }
        $this->moduleinfo = add_moduleinfo($formData, $this->course, $mform);
        return $this;
    }

    /**
     * Get the text from a specific key in the current row, and determine if it is plain or HTML format.
     * @param string $key
     * @param string $default
     * @return array with 'text' and 'format' keys
     */
    public function getTextAndFormat(string $key, string $default = ''): array {
        $value = $this->row[$key] ?? $default;
        return utils::getTextAndFormat($value);
    }

    /**
     * Get the URL to the created module.
     * @return string
     */
    public function getUrl(): string {
        return (new \moodle_url('/mod/' . $this->getEntityName() . '/view.php', ['id' => $this->getCmId()]))->out();
    }

    /** 
     * Get the display name of the created module.
     */
    public function getName(): string {
        return $this->moduleinfo->name;
    }

    /**
     * Get the instance ID of the created module.
     */
    public function getCmId(): int {
        return (int)$this->moduleinfo->coursemodule;
    }

    /**
     * Get the instance ID of the created module.
     * @return int
     */
    public function getId(): int {
        return (int)$this->moduleinfo->instance;
    }

    /**
     * Get the technical module name of the created module.
     * @return string
     */
    public function getEntityName(): string {
        return $this->moduleinfo->modulename;
    }

    /**
     * Get additional info about the created module for the log.
     * The returned string should be something like a JSON object or key=value pairs.
     * @return string
     */
    public function getAdditionalData(): string {
        return ''; // No additional data by default.
    }
}