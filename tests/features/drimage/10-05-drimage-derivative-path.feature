@regression @any @media @drimage
Feature: Drimage - Derivative path - Focal point style requests
      As a site visitor
      I want a focal-point Drimage style request for a missing image to fail cleanly
      So that the image pipeline never errors while resolving the style name.

  @check @local @development @staging @production @focal
  Scenario: Check that a focal-point style request for a missing source returns a clean not found
    Given I am an anonymous user
     When I go to "/sites/default/files/styles/drimage_improved_focal_320_240/public/no-such-file.png"
     Then the response status code should be 404
      And the response should contain "missing source file"

  @check @local @development @staging @production @focal
  Scenario: Check that a scale-only focal-point style request for a missing source returns a clean not found
    Given I am an anonymous user
     When I go to "/sites/default/files/styles/drimage_improved_focal_320_0/public/no-such-file.png"
     Then the response status code should be 404
      And the response should contain "missing source file"
