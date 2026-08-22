<?php

declare(strict_types=1);

namespace Drupal\Tests\drimage_improved\Kernel;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\TestFileCreationTrait;

/**
 * Tests the Drimage field formatter build output and image-handling modes.
 *
 * Renders an image field through the drimage_improved formatter on entity_test
 * and asserts the build: the drimage theme hook, the serialized image-handling
 * mode and its mode-specific data, multi-value output, and the empty case.
 *
 * @group drimage_improved
 */
class DrimageFormatterTest extends KernelTestBase {

  use TestFileCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'field', 'file', 'image', 'entity_test', 'drimage_improved',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('entity_test');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['field', 'image', 'drimage_improved']);

    FieldStorageConfig::create([
      'field_name' => 'field_drimage',
      'entity_type' => 'entity_test',
      'type' => 'image',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_drimage',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'label' => 'Drimage',
    ])->save();

    $this->setFormatter('scale');
  }

  /**
   * Sets the entity_test display's image field to the drimage formatter.
   */
  protected function setFormatter(string $imageHandling): void {
    \Drupal::service('entity_display.repository')
      ->getViewDisplay('entity_test', 'entity_test', 'default')
      ->setComponent('field_drimage', [
        'type' => 'drimage_improved',
        'settings' => ['image_handling' => $imageHandling],
      ])
      ->save();
  }

  /**
   * Creates a managed image file and returns it.
   */
  protected function image(int $index = 0): File {
    $source = array_values($this->getTestFiles('image'))[$index];
    $file = File::create(['uri' => $source->uri, 'status' => 1]);
    $file->save();
    return $file;
  }

  /**
   * Builds the drimage field render array for the given image files.
   */
  protected function buildField(array $files, string $alt = 'Alt text'): array {
    $value = array_map(fn(File $f) => ['target_id' => $f->id(), 'alt' => $alt], $files);
    $entity = EntityTest::create(['name' => 'test', 'field_drimage' => $value]);
    $entity->save();
    $display = \Drupal::service('entity_display.repository')->getViewDisplay('entity_test', 'entity_test', 'default');
    return $display->build($entity)['field_drimage'];
  }

  /**
   * The formatter renders each item through the drimage_formatter theme hook.
   */
  public function testFormatterBuildsDrimageTheme(): void {
    $build = $this->buildField([$this->image()]);
    $this->assertSame('drimage_formatter', $build[0]['#theme']);
    $this->assertStringContainsString('drimage', (string) $build[0]['#item_attributes'], 'The drimage container class is present.');
    $this->assertNotEmpty($build[0]['#data']['fid']);
    $this->assertNotEmpty($build[0]['#data']['original_source']);
    $this->assertSame('Alt text', $build[0]['#alt']);
    $this->assertArrayHasKey('focal_point', $build[0]['#data']);
  }

  /**
   * Each image-handling mode and its mode data land in the drimage data.
   */
  public function testImageHandlingModes(): void {
    foreach (['scale', 'aspect_ratio', 'background', 'container_size'] as $mode) {
      $this->setFormatter($mode);
      $build = $this->buildField([$this->image()]);
      $this->assertSame($mode, $build[0]['#data']['image_handling'], "mode {$mode}");
      if ($mode === 'aspect_ratio') {
        $this->assertArrayHasKey('aspect_ratio', $build[0]['#data']);
      }
      if ($mode === 'background') {
        $this->assertArrayHasKey('background', $build[0]['#data']);
      }
    }
  }

  /**
   * A multi-value image field renders one drimage item per image.
   */
  public function testMultiValueRendersEachImage(): void {
    $build = $this->buildField([$this->image(0), $this->image(1)]);
    $items = array_filter($build, fn($k) => is_int($k), ARRAY_FILTER_USE_KEY);
    $this->assertCount(2, $items);
    foreach ($items as $item) {
      $this->assertSame('drimage_formatter', $item['#theme']);
    }
  }

  /**
   * An empty image field builds no drimage items.
   */
  public function testEmptyFieldBuildsNothing(): void {
    $build = $this->buildField([]);
    $items = array_filter($build, fn($k) => is_int($k), ARRAY_FILTER_USE_KEY);
    $this->assertCount(0, $items);
  }

  /**
   * The rendered markup keeps the placeholder out of the WebP source srcset.
   *
   * A data URI in srcset makes the browser split on its comma and read the
   * remainder as a descriptor: "Failed parsing 'srcset' attribute value since
   * it has an unknown descriptor" (issue #3594133).
   */
  public function testWebpSourceHasNoPlaceholderSrcset(): void {
    $build = $this->buildField([$this->image()]);
    $html = (string) \Drupal::service('renderer')->renderRoot($build);

    $this->assertStringContainsString('type="image/webp"', $html);
    $this->assertStringNotContainsString('srcset="data:', $html);
    $this->assertDoesNotMatchRegularExpression('/srcset="[^"]* [^"]*"/', $html, 'No srcset carries a raw space.');
  }

  /**
   * A high fetch priority reaches the markup and the drimage data.
   */
  public function testFetchPriority(): void {
    \Drupal::service('entity_display.repository')
      ->getViewDisplay('entity_test', 'entity_test', 'default')
      ->setComponent('field_drimage', [
        'type' => 'drimage_improved',
        'settings' => ['image_handling' => 'scale', 'fetchpriority' => 'high'],
      ])
      ->save();

    $build = $this->buildField([$this->image()]);
    $this->assertSame('high', $build[0]['#data']['fetchpriority']);
    $html = (string) \Drupal::service('renderer')->renderRoot($build);
    $this->assertStringContainsString('fetchpriority="high"', $html);
  }

}
