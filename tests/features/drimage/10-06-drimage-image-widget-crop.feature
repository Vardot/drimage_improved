@regression @any @media @drimage
Feature: Drimage - Derivative path - Image Widget Crop style requests
      As a site visitor
      I want a crop-type Drimage style request for a missing image to fail cleanly
      So that the crop-aware image pipeline never errors while resolving the style name.

  @check @local @development @staging @production @iwc
  Scenario: Check that a crop-type style request for a missing source returns a clean not found
    Given I am an anonymous user
     When I go to "/sites/default/files/styles/drimage_improved_320_240_square/public/no-such-file.png"
     Then the response status code should be 404
      And the response should contain "missing source file"

  @check @local @development @staging @production @iwc
  Scenario: Check that the square crop type is available to editors
    Given I am a logged in user with the username "webmaster" user
     When I go to "/admin/config/media/crop"
     Then I should see "Square"
