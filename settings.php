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

// This line protects the file from being accessed by a URL directly.
defined('MOODLE_INTERNAL') || die();

// This is used for performance, we don't need to know about these settings on every page in Moodle, only when
// we are looking at the admin settings pages.
if ($ADMIN->fulltree) {
    //--- general settings -----------------------------------------------------------------------------------

    $options = array('Display Link to Player', 'Embed VM App');
    $settings->add(new admin_setting_configselect('crucible/vmapp',
        get_string('vmapp', 'crucible'), get_string('configvmapp', 'crucible'), 1, $options));

    $options = array('Dropdown', 'Searchable');
    $settings->add(new admin_setting_configselect('crucible/autocomplete',
        get_string('autocomplete', 'crucible'), get_string('configautocomplete', 'crucible'), 1, $options));

    $options = [];
    $issuers = core\oauth2\api::get_all_issuers();
    foreach ($issuers as $issuer) {
        $options[$issuer->get('id')] = s($issuer->get('name'));
    }
    $settings->add(new admin_setting_configselect('crucible/issuerid',
        get_string('issuerid', 'crucible'), get_string('configissuerid', 'crucible'), 0, $options));

    $settings->add(new admin_setting_configtext('crucible/alloyapiurl',
        get_string('alloyapiurl', 'crucible'), get_string('configalloyapiurl', 'crucible'), "", PARAM_URL, 60));

    $settings->add(new admin_setting_configtext('crucible/playerappurl',
        get_string('playerappurl', 'crucible'), get_string('configplayerappurl', 'crucible'), "", PARAM_URL, 60));

    $settings->add(new admin_setting_configtext('crucible/vmappurl',
        get_string('vmappurl', 'crucible'), get_string('configvmappurl', 'crucible'), "", PARAM_URL, 60));

    $settings->add(new admin_setting_configtext('crucible/steamfitterapiurl',
        get_string('steamfitterapiurl', 'crucible'), get_string('steamfitterapiurl', 'crucible'), "", PARAM_URL, 60));

}

