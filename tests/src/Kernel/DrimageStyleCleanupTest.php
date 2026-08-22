<?php

declare(strict_types=1);

namespace Drupal\Tests\drimage_improved\Kernel;

use Drupal\image\Entity\ImageStyle;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests that the generated Drimage image styles are deleted when they must be.
 *
 * The styles are deleted so that they regenerate on demand with the new
 * settings; see drimage issue #3394299 and #3513579.
 *
 * @group drimage_improved
 */
class DrimageStyleCleanupTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'file', 'image', 'drimage_improved'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['drimage_improved']);
  }

  /**
   * Creates a generated Drimage style and an unrelated one.
   */
  protected function seedStyles(): void {
    foreach (['drimage_improved_320_0', 'drimage_improved_focal_320_240'] as $name) {
      if (!ImageStyle::load($name)) {
        ImageStyle::create(['name' => $name, 'label' => $name])->save();
      }
    }
    if (!ImageStyle::load('unrelated_style')) {
      ImageStyle::create(['name' => 'unrelated_style', 'label' => 'Unrelated'])->save();
    }
  }

  /**
   * Counts the generated Drimage styles.
   */
  protected function drimageStyleCount(): int {
    $names = array_keys(ImageStyle::loadMultiple());
    return count(array_filter($names, fn($name) => str_starts_with($name, 'drimage_improved_')));
  }

  /**
   * Saving the Drimage settings deletes the generated styles.
   */
  public function testSettingsSaveDeletesStyles(): void {
    $this->seedStyles();
    $this->assertSame(2, $this->drimageStyleCount());

    $this->config('drimage_improved.settings')->set('threshold', 250)->save();

    $this->assertSame(0, $this->drimageStyleCount());
    $this->assertNotNull(ImageStyle::load('unrelated_style'), 'Unrelated styles are kept.');
  }

  /**
   * Installing an integration module deletes the generated styles.
   */
  public function testIntegrationModuleInstallDeletesStyles(): void {
    $this->seedStyles();
    $this->container->get('module_installer')->install(['focal_point']);
    $this->assertSame(0, $this->drimageStyleCount());
  }

  /**
   * Uninstalling an integration module deletes the generated styles.
   */
  public function testIntegrationModuleUninstallDeletesStyles(): void {
    $this->container->get('module_installer')->install(['focal_point']);
    $this->seedStyles();
    $this->assertSame(2, $this->drimageStyleCount());

    $this->container->get('module_installer')->uninstall(['focal_point']);

    $this->assertSame(0, $this->drimageStyleCount());
  }

  /**
   * Uninstalling Drimage itself deletes the generated styles.
   */
  public function testModuleUninstallDeletesStyles(): void {
    $this->seedStyles();
    $this->container->get('module_installer')->uninstall(['drimage_improved']);
    $this->assertSame(0, $this->drimageStyleCount());
    $this->assertNotNull(ImageStyle::load('unrelated_style'), 'Unrelated styles are kept.');
  }

}
