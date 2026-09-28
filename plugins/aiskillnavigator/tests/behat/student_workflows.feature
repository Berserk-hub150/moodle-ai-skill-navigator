@local @local_aiskillnavigator @javascript
Feature: Course tools support real student and teacher workflows
  In order to use the plugin in a course
  As an enrolled student and teacher
  I need practice quiz submissions and tutor interactions to work in a browser

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | Tester   | student@example.test |
      | teacher1 | Teacher   | Tester   | teacher@example.test |
    And the following "courses" exist:
      | fullname    | shortname |
      | Test course | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | teacher1 | C1     | editingteacher |
    And the AI provider is in prototype mode

  Scenario: Submit a generated practice quiz and report its result
    When I log in as "student1"
    And I open AI Skill Navigator "quizgenerator" for course "C1"
    And I set the field "topic" to "Database fundamentals"
    And I press "Generate test"
    Then I should see "Micro-test dimostrativo"
    And the AI quiz form has no client answer key
    When I click on "#q0_option0" "css_element"
    And I click on "#q1_option0" "css_element"
    And I click on "#q2_option0" "css_element"
    And I press "Submit test"
    Then I should see "Score: 3/3 (100%)"
    And the AI practice attempt count for course "C1" is "1"
    When I log out
    And I log in as "teacher1"
    And I open AI Skill Navigator "teacher" for course "C1"
    Then I should see "Student Tester"
    And I should see "100%"

  Scenario: Ask the tutor and view the recorded interaction
    When I log in as "student1"
    And I open AI Skill Navigator "tutor" for course "C1"
    And I set the field "question" to "Explain relational database keys"
    And I press "Ask AI"
    Then I should see "Risposta dimostrativa"
    When I log out
    And I log in as "teacher1"
    And I open AI Skill Navigator "tutor_analytics" for course "C1"
    Then I should see "Explain relational database keys"
    And I should see "Student Tester"

  Scenario: Publish an assessment and preserve its results after a metadata edit
    When I log in as "teacher1"
    And I open AI Skill Navigator "teacher_assessments" for course "C1"
    And I set the field "title" to "Published diagnostic"
    And I set the field "focus" to "Database fundamentals"
    And I set the field "Publish immediately to students" to "1"
    And I press "Generate and save assessment"
    Then I should see "Published diagnostic"
    When I log out
    And I log in as "student1"
    And I open AI Skill Navigator "assessment" for course "C1"
    And I follow "Start assessment"
    And I click on "#answer_0_0" "css_element"
    And I click on "#answer_1_0" "css_element"
    And I click on "#answer_2_0" "css_element"
    And I press "Submit assessment"
    Then I should see "Result: 3/3 (100%)"
    When I log out
    And I log in as "teacher1"
    And I open AI Skill Navigator "teacher_assessments" for course "C1"
    And I follow "Edit test"
    Then I should see "Questions are locked to preserve their results"
    When I set the field "title" to "Renamed diagnostic"
    And I press "Save test changes"
    Then I should see "Assessment updated"
    And I should see "Renamed diagnostic"
    And I should see "Student Tester"
    And I should see "100%"
