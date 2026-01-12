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

/**
 * Class to generate badge images with text and a checkmark.
 *
 * @package     local_attendance\content
 * @copyright   2025 Stephan Robotta <stephan.robotta@bfh.ch>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class badgeImage {
    /**
     * Text modes for the badge image.
     */
    public const TEXT_ONLY = 0;
    public const TEXT_CHECKMARK = 1;
    public const TEXT_TTF = 2;
    /**
     * Default image properties.
     */
    public const DEFAULT_WIDTH = 300;
    public const DEFAULT_HEIGHT = 300;
    public const DEFAULT_BGCOLOR = '2d89ef';
    public const DEFAULT_FGCOLOR = 'ffffff';

    /**
     * Badge text.
     * @var string
     */
    private string $text;

    /**
     * Background color in HEX.
     * @var string
     */
    private string $bgcolor;

    /**
     * Foreground color in HEX.
     * @var string
     */
    private string $fgcolor;

    /**
     * Image width in pixels.
     * @var int
     */
    private int $width;

    /**
     * Image height in pixels.
     * @var int
     */
    private int $height;

    /**
     * Text mode for the badge image.
     * @var int
     */
    private int $mode;

    /**
     * The image resource.
     * @var resource|GdImage
     */
    private $image;

    /**
     * Constructor.
     * @param string $text The text to display on the badge.
     * @param string $bgcolor Background color in HEX.
     * @param string $fgcolor Foreground color in HEX.
     * @param int $width Image width in pixels.
     * @param int $height Image height in pixels.
     * @param int|string $mode Text mode for the badge image.
     */
    public function __construct(
        string $text,
        string $bgcolor = self::DEFAULT_BGCOLOR,
        string $fgcolor = self::DEFAULT_FGCOLOR,
        int $width = self::DEFAULT_WIDTH,
        int $height = self::DEFAULT_HEIGHT,
        int|string $mode = self::TEXT_CHECKMARK
    ) {
        $this->text = $text;
        $this->bgcolor = $bgcolor;
        $this->fgcolor = $fgcolor;
        $this->width = $width;
        $this->height = $height;
        $this->mode = $this->getConstantForMode($mode);
    }

    /**
     * Convert a HEX color code to an RGB array.
     * @param string $hex
     * @return array<int>
     */
    protected function hexToRgb($hex) {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2))
        ];
    }

    /**
     * Get the constant value for a given image mode.
     *
     * @param string|int $mode The image mode as string or int.
     * @return int The constant value for the image mode.
     * @throws \moodle_exception If the image mode is invalid.
     */
    public function getConstantForMode(string|int $mode): int {
        if (is_int($mode)) {
            return $mode;
        }
        $mode = strtoupper($mode);
        if (str_starts_with($mode, 'TEXT_') && defined('self::' . $mode)) {
            return constant('self::' . $mode);
        }
        return self::TEXT_CHECKMARK; // Ignore invalid mode and use default.
    }

    /**
     * Apply checkmark and text to the image using TTF fonts (FontAwesome for checkmark).
     */
    protected function applyCheckAndText() {
        global $CFG;
        // Enable anti-aliasing
        imageantialias($this->image, true);

        $white = imagecolorallocate($this->image, 255, 255, 255);

        // Use a TTF font
        $faFont = $CFG->libdir . "/fonts/fa-solid-900.ttf";
        $textFont = $CFG->libdir . "/default.ttf";

        // Font Awesome checkmark (Unicode)
        $check = "\u{f00c}"; // FontAwesome check icon

        // ----- Draw Checkmark -----
        $checkSize = 120;
        $bbox = imagettfbbox($checkSize, 0, $faFont, $check);
        $checkWidth = $bbox[2] - $bbox[0];

        $checkX = floor(($this->width - $checkWidth) / 2);
        $checkY = 130;

        imagettftext($this->image, $checkSize, 0, $checkX, $checkY, $white, $faFont, $check);

        // ----- Draw Text -----
        $textSize = 28;
        $bbox2 = imagettfbbox($textSize, 0, $textFont, $this->text);
        $textWidth = $bbox2[2] - $bbox2[0];

        $textX = floor(($this->width - $textWidth) / 2);
        $textY = 220;

        imagettftext($this->image, $textSize, 0, $textX, $textY, $white, $textFont, $this->text);
    
    }

    /**
     * Apply text using TTF font.
     */
    protected function appyTextByTtf(int|float $fgColor) {
        global $CFG;
        // Enable anti-aliasing
        imageantialias($this->image, true);

        // Use a TTF font
        $font = $CFG->libdir . "/default.ttf";
        $fontSize = 48;

        // Get text size
        $bbox = imagettfbbox($fontSize, 0, $font, $this->text);
        $textWidth  = $bbox[2] - $bbox[0];
        $textHeight = $bbox[1] - $bbox[7];

        // Center the text
        $x = floor(($this->width - $textWidth) / 2);
        $y = floor(($this->height + $textHeight) / 2);

        // Draw the text
        imagettftext($this->image, $fontSize, 0, $x, $y, $fgColor, $font, $this->text);
    }

    /**
     * Apply text using GD built-in font.
     */
    protected function applyTextByGd(int|float $fgColor) {
        // Use built-in font
        $font = 4; // Built-in font size (1-5)
        $textWidth = imagefontwidth($font) * strlen($this->text);
        $textHeight = imagefontheight($font);

        // Center the text
        $x = floor(($this->width - $textWidth) / 2);
        $y = floor(($this->height - $textHeight) / 2);

        // Draw the text
        imagestring($this->image, $font, $x, $y, $this->text, $fgColor);
    }

    /**
     * Generate the badge image.
     */
    public function generateImage() {
        $bg = $this->hexToRgb($this->bgcolor);
        $fg = $this->hexToRgb($this->fgcolor);

        // Create image
        $this->image = imagecreatetruecolor($this->width, $this->height);

        // Allocate colors
        $bgColor = imagecolorallocate($this->image, $bg[0], $bg[1], $bg[2]);
        $fgColor = imagecolorallocate($this->image, $fg[0], $fg[1], $fg[2]);

        // Fill background
        imagefill($this->image, 0, 0, $bgColor);

        switch ($this->mode) {
            case self::TEXT_CHECKMARK:
                $this->applyCheckAndText();
                break;
            case self::TEXT_TTF:
                $this->appyTextByTtf($fgColor);
                break;
            default:
                $this->applyTextByGd($fgColor);
        }
    }

    /**
     * Get the image as a PNG blob or save to file.
     * @param string $filename If given, save the image to this file.
     * @return string|null The PNG image blob or null if saved to file.
     */
    public function getImageBlob(string $filename = ''): ?string {
        if (!isset($this->image)) {
            $this->generateImage();
        }
        if ($filename !== '') {
            imagepng($this->image, $filename);
            return null;
        }
        // Capture the output
        ob_flush();
        ob_start();
        imagepng($this->image);
        $blob = ob_get_clean();
        return $blob;
    }
}