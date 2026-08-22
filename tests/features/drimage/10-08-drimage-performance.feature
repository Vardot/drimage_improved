@regression @any @media @drimage @perf
Feature: Drimage - Performance - Page and derivative budgets
      As a site visitor
      I want Drimage pages and their generated images to arrive quickly
      So that responsive images never cost more than they save.

  @check @local @development @staging @production
  Scenario: Check that the front page loads within its budget and the image decodes
    Given I am an anonymous user
     When I go to homepage
     Then the page should load in less than 3 seconds
      And the image "[data-drimage_improved] picture img" should be loaded within 2 seconds

  @check @local @development @staging @production
  Scenario: Check that a generated derivative responds within its budget once warm
    Given I am an anonymous user
     When I go to homepage
      And I reload
     Then the page should respond in less than 2 seconds
