@regression @any @media @drimage
Feature: Drimage - Image styles - Regeneration after they are deleted
      As a site visitor
      I want images to come back after an administrator changes the Drimage settings
      So that saving settings never leaves the site without images.

  @check @local @development @staging @production
  Scenario: Verify that images regenerate after the Drimage settings are saved
    Given I am a logged in user with the username "webmaster" user
     When I go to "/admin/config/media/drimage_improved"
      And I press "edit-submit" by its "id" attribute
     Then I should see "Drimage Settings have been successfully saved."
    Given I am an anonymous user
     When I go to homepage
     Then "[data-drimage_improved] picture img" should be attached within 2 seconds
      And the image "[data-drimage_improved] picture img" should be loaded within 2 seconds
