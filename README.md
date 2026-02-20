# Crucible Plugin for Moodle

## Table of Contents

1. [Description](#description)
2. [Features](#features)
3. [Requirements](#requirements)
4. [Installation](#installation)
5. [Configuration](#configuration)
6. [Usage](#usage)
7. [License](#license)

## Description

The **Crucible Plugin for Moodle** is an activity plugin that integrates Crucible environments into the Moodle Learning Management System (LMS). It enables users to launch and access events directly from within Moodle.

>⚠️ This documentation is meant for Moodle system administrators.

## Features

- Launch Crucible environments from Moodle activities
- Embed event access within Moodle or open in a separate browser tab
- Configure event access behavior and availability through Moodle activity settings
- Authenticate users into Crucible using the configured integration method

## Requirements

1. Moodle 4.x or above
2. Crucible application stack deployed and operational
3. An OAuth2 identity provider configured for both Moodle and Crucible (we recommend [Keycloak](www.keycloak.org))

## Installation

System admin-type users or users who have the appropriate permissions should follow the procedures below to download and install the Crucible for Moodle plugin.

1. Download the plugin from this repo.
2. Extract the plugin into the `mod/crucible` directory of your Moodle instance.
3. Log into your Moodle as a site administrator or with the appropriate permissions.
4. Navigate to **Site administration**, **Plugins**, **Install plugins**.
5. Upload the plugin zip file and click **Upload this file**.
6. Click **Install plugin from the ZIP file**.
7. Follow the prompts to complete the installation process.

### Verifying your installation

1. Navigate to **Site administration**, **Plugins**, **Manage Activities**.
2. Confirm that **Crucible** appears in the list of installed activities.

## Configuration

Access configurable settings in Moodle by navigating to **Site Administration**, **Plugins**, **Crucible** (located under **Activity modules**).

| Setting                             | Description                                                                                                  |
|-------------------------------------|--------------------------------------------------------------------------------------------------------------|
| **Display Mode**                    | Choose whether Moodle embeds the VM application in the activity page or opens Player in a new tab or window. |
| **Event Template Selection Method** | Controls how instructors search for and select Event Templates when creating the activity.                   |
| **OAUTH2 Issuer**                   | Selects the identity provider Moodle uses to authenticate users into Crucible.                               |
| **Alloy API Base URL**              | API endpoint Moodle uses to create and manage environments (include `/api`, no trailing `/`).                |
| **Player Base URL**                 | Base URL students open when launching the event in Player (no trailing `/`).                                 |
| **VM App Base URL**                 | Base URL Moodle loads when embedding the VM app in the activity page (no trailing `/`).                      |
| **Steamfitter API URL**             | API endpoint that records lab events and scoring data (no trailing `/`).                                     |

## Usage

### Adding a Crucible Activity

Follow the procedures below to add a Crucible activity to a course in Moodle.

1. Navigate to your desired course and click **Add an activity or resource** (you may have to **Add Content** first).
2. Select Crucible from the **Activities** tab.
3. Complete the following:

   - **Description:** Provide a description of the Crucible activity, visible to students on the course page if enabled.
   - **Alloy Event Template:** Specify the lab or environment to launch.
   - **Display Mode:** Either embed the VM application in the activity page or link externally to the Player application.

### Launching a Crucible Activity

Follow the procedures below to launch and access a lab using the Crucible for Moodle plugin.

1. In Moodle, locate the course that contains the Crucible activity.
2. In the course, select a Crucible activity.
3. Click **Launch Lab** to access the environment.

## License

Crucible Plugin for Moodle

Copyright 2026 Carnegie Mellon University.

NO WARRANTY. THIS CARNEGIE MELLON UNIVERSITY AND SOFTWARE ENGINEERING INSTITUTE MATERIAL IS FURNISHED ON AN "AS-IS" BASIS. CARNEGIE MELLON UNIVERSITY MAKES NO WARRANTIES OF ANY KIND, EITHER EXPRESSED OR IMPLIED, AS TO ANY MATTER INCLUDING, BUT NOT LIMITED TO, WARRANTY OF FITNESS FOR PURPOSE OR MERCHANTABILITY, EXCLUSIVITY, OR RESULTS OBTAINED FROM USE OF THE MATERIAL. CARNEGIE MELLON UNIVERSITY DOES NOT MAKE ANY WARRANTY OF ANY KIND WITH RESPECT TO FREEDOM FROM PATENT, TRADEMARK, OR COPYRIGHT INFRINGEMENT.

Released under a GNU GPL 3.0-style license, please see license.txt or contact <permission@sei.cmu.edu> for full terms.

[DISTRIBUTION STATEMENT A] This material has been approved for public release and unlimited distribution. Please see Copyright notice for non-US Government use and distribution.

This Software includes and/or makes use of the following Third-Party Software subject to its own license:

Moodle (<https://docs.moodle.org/dev/License>) Copyright 1999 Martin Dougiamas.

DM20-0196
