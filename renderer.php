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
 * @package   mod_crucible
 * @copyright 2020 Carnegie Mellon University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
Crucible Plugin for Moodle
Copyright 2020 Carnegie Mellon University.
NO WARRANTY. THIS CARNEGIE MELLON UNIVERSITY AND SOFTWARE ENGINEERING INSTITUTE MATERIAL IS FURNISHED ON AN "AS-IS" BASIS. CARNEGIE MELLON UNIVERSITY MAKES NO WARRANTIES OF ANY KIND, EITHER EXPRESSED OR IMPLIED, AS TO ANY MATTER INCLUDING, BUT NOT LIMITED TO, WARRANTY OF FITNESS FOR PURPOSE OR MERCHANTABILITY, EXCLUSIVITY, OR RESULTS OBTAINED FROM USE OF THE MATERIAL. CARNEGIE MELLON UNIVERSITY DOES NOT MAKE ANY WARRANTY OF ANY KIND WITH RESPECT TO FREEDOM FROM PATENT, TRADEMARK, OR COPYRIGHT INFRINGEMENT.
Released under a GNU GPL 3.0-style license, please see license.txt or contact permission@sei.cmu.edu for full terms.
[DISTRIBUTION STATEMENT A] This material has been approved for public release and unlimited distribution.  Please see Copyright notice for non-US Government use and distribution.
This Software includes and/or makes use of the following Third-Party Software subject to its own license:
1. Moodle (https://docs.moodle.org/dev/License) Copyright 1999 Martin Dougiamas.
DM20-0196
 */

defined('MOODLE_INTERNAL') || die();

class mod_crucible_renderer extends plugin_renderer_base {

    function display_detail ($crucible) {
        $data = new stdClass();
        $data->name = $crucible->name;
        $data->intro = $crucible->intro;
        echo $this->render_from_template('mod_crucible/detail', $data);

    }

    function display_form($url, $definition) {
	$data = new stdClass();
	$data->url = $url;
	$data->definition = $definition;
	echo $this->render_from_template('mod_crucible/form', $data);

    }

    function display_link_page($player_app_url, $exerciseid) {
        $data = new stdClass();
	$data->url =  $player_app_url . '/exercise-player/' .  $exerciseid;
	$data->playerlinktext = get_string('playerlinktext', 'mod_crucible');
        // Render the data in a Mustache template.
        echo $this->render_from_template('mod_crucible/link', $data);

    }

    function display_embed_page($crucible) {
        $data = new stdClass();
        // Render the data in a Mustache template.
        echo $this->render_from_template('mod_crucible/embed', $data);

    }

    function display_history($history) {
	$table = new stdClass();
        $table->tableheaders = [
            get_string('id', 'mod_crucible'),
            get_string('status', 'mod_crucible'),
            get_string('launchdate', 'mod_crucible'),
            get_string('enddate', 'mod_crucible'),
        ];

	foreach ($history as $odx) {
	    //var_dump($odx);
	    $data = array();
	    $data[] = $odx['id'];
	    $data[] = $odx['status'];
	    $data[] = $odx['launchDate'];
	    $data[] = $odx['endDate'];
	    $table->tabledata[] = $data;
	}

        echo $this->render_from_template('mod_crucible/history', $table);
    }
}


