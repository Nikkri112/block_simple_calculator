<?php
// This file is part of Moodle - https://moodle.org/
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
// You should have received a copy of the GNU General Public License along with
// Moodle.  If not, see <https://www.gnu.org/licenses/>.
/**
 * Block simple calculator renderer.
 *
 * @package     block_simple_calculator
 * @copyright   2020 A K M Safat Shahin <safatshahin@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_simple_calculator\output;

defined('MOODLE_INTERNAL') || die();

use renderable;
use templatable;
use renderer_base;

class quiz_results implements renderable, templatable {
    public $results;

    public function __construct($results) {
        $this->results = $results;
    }

    // Метод для передачи данных в шаблон Mustache
    public function export_for_template(renderer_base $output) {
        return [
            'results'=>json_encode($this->results)
        ];
    }
}