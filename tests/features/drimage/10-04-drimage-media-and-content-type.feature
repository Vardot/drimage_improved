@regression @any @media @drimage
Feature: Drimage - Media and content type - Editorial setup
      As an editor
      I want Drimage available for image media and for content image fields
      So that images across media and content render responsively.

  @check @local @development @staging @production
  Scenario: Check the media add page offers the Image and Remote video media types
    Given I am a logged in user with the username "webmaster" user
     When I go to "/media/add"
     Then I should see "Image"
      And I should see "Remote video"

  @check @local @development @staging @production
  Scenario: Check the Drimage Test content type exposes its Drimage image field
    Given I am a logged in user with the username "webmaster" user
     When I go to "/node/add/drimage_test"
     Then I should see "Drimage image"
      And "input[type='file']" should be attached within 2 seconds
