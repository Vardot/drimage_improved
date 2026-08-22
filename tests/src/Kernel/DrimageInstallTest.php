<?php

declare(strict_types=1);

namespace Drupal\Tests\drimage_improved\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests installing Drimage together with an integration module.
 *
 * Installing drimage_improved and focal_point in one batch (as a recipe does)
 * fires hook_modules_installed() for focal_point before the container carries
 * the drimage_improved services; the hook must not fail on that.
 *
 * @group drimage_improved
 */
class DrimageInstallTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'file', 'image'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
  }

  /**
   * Drimage and focal_point install together in one batch without errors.
   */
  public function testInstallTogetherWithFocalPoint(): void {
    $this->container->get('module_installer')->install(['focal_point', 'drimage_improved']);
    $this->assertTrue($this->container->get('module_handler')->moduleExists('drimage_improved'));
    $this->assertTrue($this->container->get('module_handler')->moduleExists('focal_point'));
    $this->assertTrue($this->container->has('drimage_improved.image_style_repository'));
  }

  /**
   * An integration module can be uninstalled again with Drimage still on.
   */
  public function testUninstallIntegrationModule(): void {
    $this->container->get('module_installer')->install(['focal_point', 'drimage_improved']);
    $this->container->get('module_installer')->uninstall(['focal_point']);
    $this->assertFalse($this->container->get('module_handler')->moduleExists('focal_point'));
    $this->assertTrue($this->container->get('module_handler')->moduleExists('drimage_improved'));
  }

}
