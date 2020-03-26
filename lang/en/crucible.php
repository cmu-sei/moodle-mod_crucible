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
 * Language file.
 *
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

$string['modulename'] = "Crucible";
$string['modulename_help'] = 'Displays Crucible labs and VM consoles';
$string['modulename_link'] = 'mod/crucible/view';
$string['modulenameplural'] = 'Crucibles';
$string['pluginname'] = 'Crucible';

// plugin settings

$string['alloyapiurl'] = 'Alloy API Base URL';
$string['vmapp'] = 'Display';
$string['vmappurl'] = 'VM App Base URL';
$string['playerappurl'] = 'Player Base URL';
$string['issuerid'] = 'OAUTH2 Issuer';
$string['autocomplete'] = 'Lab Selection Method';
$string['definition'] = 'Alloy Lab';
$string['selectname'] = 'Search for a lab by name';

$string['configissuerid'] = 'This is the integer value for the issuer.';
$string['configvmapp'] = 'This determines whether the VM app is emebedd or whether a link to player is displayed';
$string['configvmappurl'] = 'Base URL for VM app instance without trailing "/".';
$string['configplayerappurl'] = 'Base URL for Player instance without trailing "/".';
$string['configalloyapiurl'] = 'Alloy API Base URL address without trailing "/".';
$string['configdefinition'] = 'Alloy definition ID for the lab to be launched.';
$string['configautocomplete'] = 'Display list of labs in a dropdown or a searchable text box.';

// activity settings

$string['vmapp_help'] = 'This determines whether the VM app is emebedd or whether a link to player is displayed';
$string['definition_help'] = 'This is the lab definition in Alloy.';

$string['definition'] = 'Alloy Definition';

$string['pluginadministration'] = 'Crucible administration';
$string['eventattemptended'] = "Attempt ended";

$string['id'] = 'Alloy Implementation ID';
$string['status'] = 'Status';
$string['launchdate'] = 'Launch Date';
$string['enddate'] = 'End Date';
$string['tablecaption'] = 'History';
$string['playerlinktext'] = 'Click here to open player in a new tab';
