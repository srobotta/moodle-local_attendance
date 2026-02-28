@local @local_attendance @_file_upload
Feature: Upload attendance courses from CSV
  In order to create attendance courses from course templates
  As an admin
  I need to be able to upload a CSV file with course and module definitions

  Background:
    Given the following "courses" exist:
      | fullname       | shortname | category |
      | Test Course    | testcourse| 0        |
    And the following "users" exist:
      | username | firstname | lastname | email            |
      | student1 | Alice     | Smith    | alice@example.com    |
      | student2 | Bob       | Jones    | bob@example.com      |
      | trainer1 | Charlie   | Brown    | charlie@example.com  |
    And the following "local_attendance > attendance course setup" exist:
      | sourcecourse | student1username | student2username | trainerusername |
      | testcourse   | student1         | student2         | trainer1        |

  @javascript
  Scenario: Upload simple attendance course with two attendance dates
    Given I log in as "admin"
    And I navigate to "Plugins > Local plugins > Attendance Course Creator" in site administration
    When I upload "local/attendance/tests/fixtures/simple_attendance.csv" file to "Upload CSV file" filemanager
    And I set the field "Course generic suffix" to "Attendance"
    And I set the field "CSV delimiter" to "Semicolon (;)"
    And I press "Import"
    Then I should see "Import successful"
    And I should see "Line 4: Course id:"
    And I should see "Test Course (Attendance)"
    And I should see "Day 1 Attendance"
    And I should see "Day 2 Attendance"
    And I should see "Confirmation of attendance"

  @javascript
  Scenario: Upload a full featured attendance course with three attendance dates
    Given I log in as "admin"
    And I navigate to "Plugins > Local plugins > Attendance Course Creator" in site administration
    When I upload "local/attendance/tests/fixtures/my-badge.png" file to "Additional content files" filemanager
    And I set the field "Course generic suffix" to "Attending"
    And I upload "local/attendance/tests/fixtures/full_featured.csv" file to "Upload CSV file" filemanager and scroll
    And I press "Import"
    Then I should see "Import successful"
    And I should see "Line 5: Course id:"
    And I should see "Test Course (Attending)"
    And I should see "Attendance confirmation for 16.02.2027"
    When I am on "Test Course (Attending)" course homepage
    Then I should see "Attendance confirmation for 16.02.2027"
    And I should see "Attendance confirmation for 18.02.2027"
    And I should see "Attendance confirmation for 22.02.2027"
