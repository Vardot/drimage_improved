@regression @any @media @drimage
Feature: Drimage - Responsive image formatter - Front-end rendering
      As a site visitor
      I want images rendered by Drimage to appear as responsive, lazy images
      So that pages stay fast and images fit their container on any screen.

  @check @local @development @staging @production
  Scenario: Check that a Drimage image renders its responsive container and image on the homepage
    Given I am an anonymous user
     When I go to homepage
     Then "[data-drimage_improved]" should be attached within 2 seconds
      And "[data-drimage_improved] picture img" should be attached within 2 seconds

  @check @local @development @staging @production
  Scenario: Check that a Drimage image ships a no-JavaScript fallback image
    Given I am an anonymous user
     When I go to homepage
     Then "[data-drimage_improved] noscript, [data-drimage_improved] + noscript" should be attached within 2 seconds

  @check @local @development @staging @production @webp-on
  Scenario: Check that a Drimage image offers a WebP source when core WebP is enabled
    Given I am an anonymous user
     When I go to homepage
     Then "[data-drimage_improved] picture source[type='image/webp']" should be attached within 2 seconds

  @check @local @development @staging @production @webp-off
  Scenario: Check that a Drimage image offers no WebP source when core WebP is disabled
    Given I am an anonymous user
     When I go to homepage
     Then "[data-drimage_improved] picture img" should be attached within 2 seconds
      And "[data-drimage_improved] picture source[type='image/webp']" should not be attached within 2 seconds
