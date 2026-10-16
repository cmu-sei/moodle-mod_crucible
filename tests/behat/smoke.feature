@mod @mod_crucible
Feature: Crucible pages that work without an Alloy backend
  In order to trust the Crucible activity on a new site
  As an administrator or teacher
  I need its settings, course listing and deployment management pages to work

  # CI has no OAuth2 issuer, so the activity view page can only be checked for the
  # message it shows in that case. The add/edit form is not covered here.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Terry     | Teacher  | teacher1@example.com |
      | student1 | Sam       | Student  | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | name  | course | idnumber  |
      | crucible | Lab 1 | C1     | crucible1 |

  Scenario: An administrator can change the Crucible settings
    Given I log in as "admin"
    When I navigate to "Plugins > Activity modules > Crucible" in site administration
    Then I should see "No OAuth 2 services exist yet."
    And I set the following fields to these values:
      | Event Template Selection Method  | Dropdown |
      | Maximum event extension interval | 90       |
    And I press "Save changes"
    Then I should see "Changes saved"
    And the field "Event Template Selection Method" matches value "Dropdown"
    And the field "Maximum event extension interval" matches value "90"

  Scenario: A student sees the Crucible activity on the course page
    Given I log in as "student1"
    When I am on "Course 1" course homepage
    Then I should see "Lab 1"

  Scenario: A student sees the Crucible activity in the course activity index
    Given I am on the "C1" "mod_crucible > Index" page logged in as "student1"
    Then I should see "Lab 1" in the "generaltable" "table"

  Scenario: A student opening an activity on a site without an OAuth2 issuer is told why
    Given I am on the "Lab 1" "crucible activity" page logged in as "student1"
    Then I should see "Crucible cannot connect to Alloy because no OAuth2 issuer is selected"

  @javascript
  Scenario: A teacher selects students and opens the schedule dialogue
    Given I am on the "Lab 1" "mod_crucible > Manage deployments" page logged in as "teacher1"
    Then I should see "Manage Deployments"
    And I should see "None" in the "Sam Student" "table_row"
    And the "Deploy Selected Now" "button" should be disabled
    And the "Schedule Selected..." "button" should be disabled
    When I click on ".user-checkbox" "css_element" in the "Sam Student" "table_row"
    Then the "Deploy Selected (1)" "button" should be enabled
    And the "Schedule Selected (1)" "button" should be enabled
    When I press "Schedule Selected (1)"
    Then I should see "Moodle timezone:" in the "Schedule Selected (1)" "dialogue"
    And I click on "Cancel" "button" in the "Schedule Selected (1)" "dialogue"
    And I should not see "Moodle timezone:"
    When I press "Deselect All"
    Then the "Deploy Selected Now" "button" should be disabled

  @javascript
  Scenario: A teacher filters the deployment list by role
    Given I am on the "Lab 1" "mod_crucible > Manage deployments" page logged in as "teacher1"
    And I should see "Terry Teacher" in the ".mod-crucible-users-table" "css_element"
    When I set the field "rolefilter[]" to "Student"
    And I press "Filter"
    Then I should see "Sam Student" in the ".mod-crucible-users-table" "css_element"
    And I should not see "Terry Teacher" in the ".mod-crucible-users-table" "css_element"
