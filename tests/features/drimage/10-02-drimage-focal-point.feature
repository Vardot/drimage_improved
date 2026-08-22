@regression @any @media @drimage
Feature: Drimage - Focal point derivatives - Image generation
      As a site visitor
      I want focal-point Drimage images to load their generated derivative
      So that images actually appear instead of staying broken.

  @check @local @development @staging @production @focal
  Scenario: Check that a focal-point Drimage image loads its generated derivative on the homepage
    Given I am an anonymous user
     When I go to homepage
     Then the image "[data-drimage_improved] picture img" should be loaded within 2 seconds
