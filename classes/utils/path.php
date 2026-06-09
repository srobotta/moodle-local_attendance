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
declare(strict_types=1);

namespace local_attendance\utils;

/**
 * Utility class for path handling that is used for resolving the
 * file arguments in the CLI context that they point to the correct
 * location on the filessystem.
 *
 * @package     local_attendance
 * @copyright   2026 Stephan Robotta <stephan.robotta@bfh.ch>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class path {
    /**
     * Get the original current working directory, preferring
     * the preserved PWD environment variable if available.
     */
    protected static function get_original_cwd(): string {
        // Prefer preserved PWD (if sudo -E was used)
        $pwd = getenv('PWD');
        if ($pwd && is_dir($pwd)) {
            return $pwd;
        }
        // Fallback to actual working directory
        return getcwd();
    }

    /**
     * Expand a path that may start with ~ to the user's home directory.
     * @param string $path
     * @return string
     */
    protected static function expand_tilde(string $path): string {
        if ($path[0] !== '~') {
            return $path;
        }

        $home = getenv('HOME');

        // If running under sudo, try original user
        if (!$home && isset($_SERVER['SUDO_USER'])) {
            $info = posix_getpwnam($_SERVER['SUDO_USER']);
            if ($info && isset($info['dir'])) {
                $home = $info['dir'];
            }
        }
        if (!$home) {
            return $path; // fallback: leave unchanged
        }
        return $home . substr($path, 1);
    }

    /**
     * Normalize a path by resolving . and .. components.
     * @param string $path
     * @return string
     */
    protected static function normalize_path(string $path): string {
        $parts = [];
        $path = str_replace('\\', '/', $path);

        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);
            } else {
                $parts[] = $part;
            }
        }
        return '/' . implode('/', $parts);
    }

    /**
     * Resolve a path by expanding ~, making it absolute, normalizing it,
     * and resolving the real path.
     * @param string $input
     * @return string
     */
    public static function resolve_path(string $input): string {
        // Check if its and absolute windows path, then return is as we don't want to mess with it.
        if (substr($input, 1, 2) === ':\\' || str_starts_with($input, '\\\\')) {
            return $input;
        }

        // Step 1: expand ~
        $input = self::expand_tilde($input);

        // Step 2: absolute path?
        if (str_starts_with($input, '/')) {
            $path = $input;
        } else {
            $cwd = self::get_original_cwd();
            $path = $cwd . DIRECTORY_SEPARATOR . $input;
        }

        // Step 3: normalize (handles .. and .)
        $path = self::normalize_path($path);

        // Step 4: resolve real path if it exists
        $real = realpath($path);
        if ($real !== false) {
            return $real;
        }

        // Step 5: fallback (non-existent file)
        return $path;
    }
}
