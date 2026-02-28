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

namespace local_attendance\content;

use local_attendance\utils\utils;
use local_attendance\modcreate;

/**
 * Class to create badges with criteria in a given course.
 *
 * @package     local_attendance\content
 * @copyright   2025 Stephan Robotta <stephan.robotta@bfh.ch>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class badge extends modcreate {
    /**
     * The created badge.
     * @var \core_badges\badge
     */
    private \badge $badge;

    /**
     * Create the badge in the given course.
     * @param array $data The data for creating the badge, including name, description, image parameters and criteria parameters.
     * @return \core_badges\badge The created badge.
     * @throws \moodle_exception
     */
    public function create(array $data): modcreate {
        global $CFG;
        // Create badge in the given course.
        $context = \context_course::instance($this->course->id);
        // Check capabilities for creating a badge in a course and change badge criteria.
        require_capability('moodle/badges:createbadge', $context);
        require_capability('moodle/badges:configurecriteria', $context);

        // Some fields are required for badge creation and are independent of the csv data.
        $data['courseid'] = $this->course->id;
        $data['type'] = BADGE_TYPE_COURSE;
        $data['action'] = 'new';
        // If there is no name or description given, use default values from the language strings.
        if (!\array_key_exists('name', $data)) {
            $data['name'] = get_string('badge_defaultname', 'local_attendance');
        }
        if (!\array_key_exists('description', $data)) {
            $data['description'] = get_string(
                'badge_defaultdescription',
                'local_attendance',
                $this->course->fullname
            );
        }
        // Thow an error if there is an invalid criteriatype given.
        $criteriaType = -1;
        if (\array_key_exists('criteriatype', $this->row)) {
            $criteriaType = $this->getConstantForCriteriaType($this->row['criteriatype']);
            if ($criteriaType === -1) {
                $a = [
                    'value' => $this->row['criteriatype'],
                    'column' => 'criteriatype'
                ];
                throw new \moodle_exception('ex_invalidvalue', 'local_attendance', '', $a);
            }
        }
        // Collect all possible fields from the badge form.
        $form = new \core_badges\form\badge('', $data);
        $formdata = utils::mergeData($form, $data);
        $this->badge = \badge::create_badge($formdata, $this->course->id);
        // Create badge image. In the form the image is mandatory, so we create one here.
        if (\array_key_exists('imagefile', $data)) {
            // Use uploaded image file, use this function instead of badges_process_badge_image because
            // the image is deleted after processing. We might need it again when importing other badges
            // from the same CSV file.
            require_once($CFG->libdir. '/gdlib.php');
            \process_new_icon($this->badge->get_context(), 'badges', 'badgeimage', $this->badge->id, $data['imagefile']);
        } else {
            // Create image based on given parameters.
            $img = new badgeImage(
                $data['imagecaption'] ?? '',
                $data['bgcolor'] ?? badgeImage::DEFAULT_BGCOLOR,
                $data['fgcolor'] ?? badgeImage::DEFAULT_FGCOLOR,
                $data['width'] ?? badgeImage::DEFAULT_WIDTH,
                $data['height'] ?? badgeImage::DEFAULT_HEIGHT,
                $data['imagemode'] ?? badgeImage::TEXT_CHECKMARK
            );
            $imgFile = $CFG->tempdir . '/local_attendance_badge_' . time() . '.png';
            $img->getImageBlob($imgFile);
            \badges_process_badge_image($this->badge, $imgFile);
        }

        if ($criteriaType !== -1) {
            // Add criteria if specified in the current row.
            $this->addCriteria($criteriaType);
        }
        if (!\array_key_exists('badgedisable', $this->row)) {
            // Enable the badge.
            $this->badge->set_status(BADGE_STATUS_ACTIVE);
        }
        return $this;
    }

    /**
     * Add criteria to the created badge, taken from the input data.
     * @param int $criteriaType
     * @throws \moodle_exception
     */
    public function addCriteria(int $criteriaType): void {
        $context = $this->badge->get_context();
        require_capability('moodle/badges:configurecriteria', $context);
        $params = [
            'criteriatype' => $criteriaType,
            'badgeid' => $this->badge->id,
            'course' => $this->course->id,
        ];

        // If this is the first criteria added, we also need to add the overall criteria.
        if (count($this->badge->criteria) === 0) {
            $criteriaOverall = \award_criteria::build([
                'criteriatype' => BADGE_CRITERIA_TYPE_OVERALL,
                'badgeid' => $this->badge->id,
            ]);
            $criteriaOverall->save(['agg' => BADGE_CRITERIA_AGGREGATION_ALL]);
        }

        // Add specific criteria to the badge.
        $criteria = \award_criteria::build($params);
        foreach ($criteria->optional_params as $param) {
            if (isset($this->row[$param])) {
                $params[$param . '_' . $this->course->id] = $this->row[$param];
            }
        }
        if (isset($this->row['description'])) {
            $params['description'] = utils::getTextAndFormat($this->row['description']);
        }
        if ($criteria instanceof \award_criteria_courseset) {
            $id = $criteria->add_courses([$this->course->id]);
            $criteria->id = $id;
        }
        else if ($criteria instanceof \award_criteria_course) {
            $params['course_' . $this->course->id] = $this->course->id;
        }
        $criteria->save($params);
    }

    /**
     * Get the constant value for a given criteria type.
     *
     * @param string|int $type The criteria type as string or int.
     * @return int The constant value for the criteria type.
     * @throws \moodle_exception If the criteria type is invalid.
     */
    public function getConstantForCriteriaType(string|int $type): int {
        if (is_int($type)) {
            return $type;
        }
        $type = strtoupper($type);
        if (str_starts_with($type, 'BADGE_CRITERIA_TYPE_') && defined($type)) {
            return constant($type);
        }
        return -1; // Trigger exception for invalid type.
    }

    /**
     * Get the created badge.
     * @return \core_badges\badge
     */
    public function getBadge(): \core_badges\badge {
        return $this->badge;
    }

        /**
     * Get the URL to the created module.
     * @return string
     */
    public function getUrl(): string {
        return (new \moodle_url('/badges/overview.php', ['id' => $this->getId()]))->out();
    }

    /** 
     * Get the display name of the created module.
     */
    public function getName(): string {
        return $this->getBadge()->name;
    }

    /**
     * Get the instance ID of the created module.
     */
    public function getCmId(): int {
        return 0; // Badges do not have course modules.
    }

    /**
     * Get the instance ID of the created module.
     * @return int
     */
    public function getId(): int {
        return (int)$this->getBadge()->id;
    }

    /**
     * Get the technical module name of the created module.
     * @return string
     */
    public function getEntityName(): string {
        return 'badge';
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