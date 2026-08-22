<?php

declare(strict_types=1);

namespace Drupal\Tests\drimage_improved\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the Drimage image style name selection across integration modules.
 *
 * Covers DrimageManager::getDrimageId() for the plain, focal_point and
 * image_widget_crop cases, exercising the enabled/disabled matrix by installing
 * the integration modules at runtime.
 *
 * @group drimage_improved
 */
class DrimageManagerStyleNameTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'file', 'image', 'drimage_improved'];

  /**
   * Invokes the protected DrimageManager::getDrimageId().
   */
  protected function drimageId(array $dimensions, ?string $iwc = NULL): string {
    $manager = \Drupal::service('drimage_improved.manager');
    // DrimageManager initializes its $moduleHandler property lazily through the
    // public code path; prime it before reflecting the protected method.
    $primer = new \ReflectionMethod($manager, 'moduleHandler');
    $primer->setAccessible(TRUE);
    $primer->invoke($manager);
    $method = new \ReflectionMethod($manager, 'getDrimageId');
    $method->setAccessible(TRUE);
    return $method->invoke($manager, $dimensions, $iwc);
  }

  /**
   * With no integration module the plain style name is used.
   */
  public function testPlainStyleName(): void {
    $this->assertSame('drimage_improved_100_50', $this->drimageId([100, 50]));
  }

  /**
   * With focal_point enabled the focal style name is used.
   *
   * This is the module-matrix guard for the focal_point handling that the
   * DrimageSubscriber off-by-one (issue #3618235) depended on.
   */
  public function testFocalPointStyleName(): void {
    \Drupal::service('module_installer')->install(['focal_point']);
    $this->assertSame('drimage_improved_focal_100_50', $this->drimageId([100, 50]));
  }

  /**
   * With image_widget_crop enabled and a valid crop type the IWC style is used.
   */
  public function testImageWidgetCropStyleName(): void {
    \Drupal::service('module_installer')->install(['image_widget_crop']);
    \Drupal::entityTypeManager()->getStorage('crop_type')->create([
      'id' => 'square',
      'label' => 'Square',
    ])->save();

    $this->assertSame('drimage_improved_100_50_square', $this->drimageId([100, 50], 'square'));
    // An unknown crop type falls back to the plain (or focal) name.
    $this->assertSame('drimage_improved_100_50', $this->drimageId([100, 50], 'missing'));
  }

}
