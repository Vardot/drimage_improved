@regression @any @media @drimage
Feature: Drimage - Image generation in progress - Concurrent requests
      As a site visitor
      I want an image that someone else is generating right now to still reach me
      So that I never see a broken image because several visitors asked for it at once.

  # Issue #3637295. Core answers "Image generation in progress" at once while
  # another request holds its lock, and image tags never retry. Drimage holds a
  # lock of its own while it generates: another request waits for it up to 2
  # seconds and delivers the image, or answers a plain 503.
  # The lock is held by the hidden testing module drimage_improved_lock_test and
  # expires on its own, so a failing scenario never blocks the next one.

  @check @local @development
  Scenario: Check that a visitor gets the image when another request finishes generating it first
    Given I am a logged in user with the username "webmaster" user
      And another request is generating the drimage image "public://drimage-test/drimage-sample.png" for 1.5 seconds
     When a visitor requests that drimage image
     Then the visitor should get the drimage image
      And the drimage image request should have waited at least 0.5 seconds

  @check @local @development
  Scenario: Check that a visitor is asked to try again, not shown an error, while generation runs too long
    Given I am a logged in user with the username "webmaster" user
      And another request is generating the drimage image "public://drimage-test/drimage-sample.png" for 4 seconds
     When a visitor requests that drimage image
     Then the visitor should be asked to try the drimage image again shortly
      And the drimage image request should have waited at least 2 seconds

  @check @local @development
  Scenario: Check that several visitors asking for a new image size at the same moment all get the image
    Given I am a logged in user with the username "webmaster" user
      And the drimage image "public://drimage-test/drimage-sample.png" has not been generated yet
     When 6 visitors request that drimage image at the same time
     Then every visitor should get the drimage image

  @check @local @development @stress
  Scenario: Stress - many visitors ask for a new image size at the same moment, round after round
    Given I am a logged in user with the username "webmaster" user
     When 8 visitors request the drimage image "public://drimage-test/drimage-sample.png" at the same time, 10 times over
     Then every visitor should get the drimage image

  @check @local @development @stress
  Scenario: Stress - many visitors wait together while another request is still generating the image
    Given I am a logged in user with the username "webmaster" user
     When 8 visitors request the drimage image "public://drimage-test/drimage-sample.png" at the same time, 5 times over, while another request generates it for 1 second
     Then every visitor should get the drimage image
