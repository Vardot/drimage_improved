@regression @any @media @drimage
Feature: Drimage - Settings form - Access and configuration persistence
      As an administrator
      I want to reach the Drimage settings form, change a setting and have it stick
      So that image style generation follows my configuration, while visitors cannot.

  @check @local @development @staging @production
  Scenario: Check that an anonymous visitor cannot open the Drimage settings form
    Given I am an anonymous user
     When I go to "/admin/config/media/drimage_improved"
     Then the response status code should be 403

  @check @local @development @staging @production
  Scenario: Check that an administrator can open the Drimage settings form
    Given I am a logged in user with the username "webmaster" user
     When I go to "/admin/config/media/drimage_improved"
     Then I should see "Minimum difference per image style"
      And "input[name='threshold']" should be attached within 2 seconds
      And "input[name='downscale']" should be attached within 2 seconds

  @check @local @development @staging @production
  Scenario: Verify that a changed Drimage setting persists after saving and reloading
    Given I am a logged in user with the username "webmaster" user
     When I go to "/admin/config/media/drimage_improved"
      And I fill in "Maximum image style width" with "3000"
      And I press "edit-submit" by its "id" attribute
     Then I should see "Drimage Settings have been successfully saved."
     When I reload
     Then "input[name='downscale']" should have value "3000" within 2 seconds
     When I fill in "Maximum image style width" with "3840"
      And I press "edit-submit" by its "id" attribute
     Then I should see "Drimage Settings have been successfully saved."
