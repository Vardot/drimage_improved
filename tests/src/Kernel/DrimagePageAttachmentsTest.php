<?php

declare(strict_types=1);

namespace Drupal\Tests\drimage_improved\Kernel;

use Drupal\image\Entity\ImageStyle;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests hook_drimage_improved_page_attachments() dimension parsing.
 *
 * Covers the drupalSettings dimensions the front-end JS reads, the focal_point
 * name handling, the scale-only skip, and the injected no-JS style.
 *
 * @group drimage_improved
 */
class DrimagePageAttachmentsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'file', 'image', 'drimage_improved'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['drimage_improved']);
  }

  /**
   * Runs the page-attachments hook and returns the attachments array.
   */
  protected function attachments(): array {
    $attachments = [];
    drimage_improved_page_attachments($attachments);
    return $attachments;
  }

  /**
   * A drimage image style with a height is published to drupalSettings.
   */
  public function testDrimageStyleDimensionsAttached(): void {
    ImageStyle::create(['name' => 'drimage_improved_400_300', 'label' => 'Drimage 400x300'])->save();
    // A scale-only style (height 0) must be skipped.
    ImageStyle::create(['name' => 'drimage_improved_800_0', 'label' => 'Drimage 800 scale'])->save();
    // A non-drimage style must be ignored.
    ImageStyle::create(['name' => 'thumbnail_generic', 'label' => 'Thumb'])->save();

    $settings = $this->attachments()['#attached']['drupalSettings']['drimage_improved'];
    $names = array_column($settings['dimentions'], 'name');

    $this->assertContains('drimage_improved_400_300', $names);
    $this->assertNotContains('drimage_improved_800_0', $names, 'Scale-only styles are skipped.');
    $this->assertNotContains('thumbnail_generic', $names, 'Non-drimage styles are ignored.');

    $entry = array_values(array_filter($settings['dimentions'], fn($d) => $d['name'] === 'drimage_improved_400_300'))[0];
    $this->assertSame('400', (string) $entry['width']);
    $this->assertSame('300', (string) $entry['height']);
    $this->assertSame(60, $settings['ratio_distortion']);
  }

  /**
   * With focal_point enabled the "focal_" segment is stripped before parsing.
   */
  public function testFocalStyleDimensionsAttached(): void {
    \Drupal::service('module_installer')->install(['focal_point']);
    ImageStyle::create(['name' => 'drimage_improved_focal_640_480', 'label' => 'Drimage focal'])->save();

    $settings = $this->attachments()['#attached']['drupalSettings']['drimage_improved'];
    $entry = array_values(array_filter($settings['dimentions'], fn($d) => $d['name'] === 'drimage_improved_focal_640_480'))[0];
    $this->assertSame('640', (string) $entry['width']);
    $this->assertSame('480', (string) $entry['height']);
  }

  /**
   * The hook injects the no-JavaScript style that hides the swap target.
   */
  public function testNoscriptStyleAttached(): void {
    $head = $this->attachments()['#attached']['html_head'];
    $found = FALSE;
    foreach ($head as $item) {
      if (($item[1] ?? '') === 'noscript') {
        $found = TRUE;
        $this->assertStringContainsString('.drimage-image', $item[0]['#value']);
      }
    }
    $this->assertTrue($found, 'A noscript style element is attached.');
  }

}
