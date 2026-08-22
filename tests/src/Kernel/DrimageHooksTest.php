<?php

declare(strict_types=1);

namespace Drupal\Tests\drimage_improved\Kernel;

use Drupal\drimage_improved\Controller\ImageStyleListBuilder;
use Drupal\drimage_improved\Controller\ImageStyleWithPipelineListBuilder;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests Drimage hook implementations.
 *
 * Covers hook_theme() and the imageapi_optimize-aware hook_entity_type_alter()
 * list-builder swap (integration module enabled vs disabled).
 *
 * @group drimage_improved
 */
class DrimageHooksTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'file', 'image', 'drimage_improved'];

  /**
   * Hook_theme() registers the drimage_formatter theme hook.
   */
  public function testHookThemeRegistersFormatter(): void {
    $theme = drimage_improved_theme();
    $this->assertArrayHasKey('drimage_formatter', $theme);
    $this->assertArrayHasKey('data', $theme['drimage_formatter']['variables']);
    $this->assertArrayHasKey('core_webp', $theme['drimage_formatter']['variables']);
  }

  /**
   * Without imageapi_optimize the plain image-style list builder is used.
   */
  public function testListBuilderWithoutImageapiOptimize(): void {
    $class = \Drupal::entityTypeManager()->getDefinition('image_style')->getListBuilderClass();
    $this->assertSame(ImageStyleListBuilder::class, $class);
  }

  /**
   * With imageapi_optimize enabled the pipeline-aware list builder is used.
   */
  public function testListBuilderWithImageapiOptimize(): void {
    \Drupal::service('module_installer')->install(['imageapi_optimize']);
    $class = \Drupal::entityTypeManager()->getDefinition('image_style')->getListBuilderClass();
    $this->assertSame(ImageStyleWithPipelineListBuilder::class, $class);
  }

}
