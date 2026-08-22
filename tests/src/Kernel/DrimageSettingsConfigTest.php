<?php

declare(strict_types=1);

namespace Drupal\Tests\drimage_improved\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the Drimage default settings and their config schema.
 *
 * @group drimage_improved
 */
class DrimageSettingsConfigTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'file', 'image', 'drimage_improved'];

  /**
   * {@inheritdoc}
   */
  protected $strictConfigSchema = TRUE;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['drimage_improved']);
  }

  /**
   * The module ships sane, schema-valid default settings.
   */
  public function testDefaultSettings(): void {
    $settings = $this->config('drimage_improved.settings');

    // Installing config under $strictConfigSchema already asserts the values
    // validate against config/schema/drimage_improved.schema.yml; assert the
    // shipped defaults on top of that.
    $this->assertSame(200, $settings->get('threshold'));
    $this->assertSame(60, $settings->get('ratio_distortion'));
    $this->assertSame(320, $settings->get('upscale'));
    $this->assertSame(3840, $settings->get('downscale'));
    $this->assertTrue((bool) $settings->get('core_webp'));
    $this->assertFalse((bool) $settings->get('imageapi_optimize_webp'));
    $this->assertSame('#ffffff', $settings->get('placeholder_color'));
  }

  /**
   * Values written through the config API round-trip and stay schema-valid.
   */
  public function testSettingsRoundTrip(): void {
    $this->config('drimage_improved.settings')
      ->set('threshold', 250)
      ->set('downscale', 3000)
      ->set('core_webp', FALSE)
      ->save();

    $settings = $this->config('drimage_improved.settings');
    $this->assertSame(250, $settings->get('threshold'));
    $this->assertSame(3000, $settings->get('downscale'));
    $this->assertFalse((bool) $settings->get('core_webp'));
  }

}
