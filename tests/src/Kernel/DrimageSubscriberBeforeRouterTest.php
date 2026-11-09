<?php

declare(strict_types=1);

namespace Drupal\Tests\drimage_improved\Kernel;

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Test\TestDatabase;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\TestFileCreationTrait;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the first request for a Drimage style that does not exist yet.
 *
 * Regression test for #3626466: the router 404ed the missing style before the
 * Drimage subscriber could create it.
 *
 * @group drimage_improved
 */
class DrimageSubscriberBeforeRouterTest extends KernelTestBase {

  use TestFileCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'file', 'image', 'crop', 'focal_point', 'drimage_improved',
  ];

  /**
   * A real public files directory, relative to the Drupal root.
   */
  protected string $publicPath;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // The default vfs:// path cannot be put in a request URL.
    $this->publicPath = (new TestDatabase($this->databasePrefix))->getTestSitePath() . '/files';
    $this->setSetting('file_public_path', $this->publicPath);
    \Drupal::service('file_system')->mkdir($this->publicPath, NULL, TRUE);
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('crop');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['system', 'image', 'focal_point', 'drimage_improved']);
    $this->container->get('router.builder')->rebuild();
    $source = current($this->getTestFiles('image'));
    $dir = 'public://drimage';
    \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY);
    \Drupal::service('file_system')->saveData(file_get_contents($source->uri), 'public://drimage/test.png', FileExists::Replace);
    File::create(['uri' => 'public://drimage/test.png', 'status' => 1])->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    \Drupal::service('file_system')->deleteRecursive(dirname($this->publicPath));
    parent::tearDown();
  }

  /**
   * Sends a derivative request through the full HTTP kernel, router included.
   */
  protected function handle(string $style, string $file): string {
    $request = Request::create('/' . $this->publicPath . '/styles/' . $style . '/public/' . $file);
    $response = $this->container->get('http_kernel')->handle($request);
    return $response->getStatusCode() . ' ' . $response->headers->get('Location');
  }

  /**
   * A style that does not exist yet answers 200 on the first request.
   */
  public function testMissingStyleFirstRequest(): void {
    $style = 'drimage_improved_focal_320_240';
    $this->assertNull(ImageStyle::load($style));
    $this->assertSame('200 ', $this->handle($style, 'drimage/test.png'));
    $this->assertNotNull(ImageStyle::load($style));
  }

  /**
   * A missing source file still answers 404.
   */
  public function testMissingSourceStill404(): void {
    $this->assertSame('404 ', $this->handle('drimage_improved_focal_320_240', 'drimage/missing.png'));
  }

}
