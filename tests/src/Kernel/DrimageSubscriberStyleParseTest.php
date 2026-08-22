<?php

declare(strict_types=1);

namespace Drupal\Tests\drimage_improved\Kernel;

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\TestFileCreationTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Tests that derivatives still generate with image_widget_crop installed.
 *
 * Regression test for #3513579: once image_widget_crop was installed next to
 * focal_point, every focal-point style request was parsed as a crop-type
 * request and no derivative could be generated anymore.
 *
 * @group drimage_improved
 */
class DrimageSubscriberStyleParseTest extends KernelTestBase {

  use TestFileCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'file', 'image', 'crop', 'focal_point', 'image_widget_crop', 'drimage_improved',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('crop');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['image', 'focal_point', 'drimage_improved']);
    $source = current($this->getTestFiles('image'));
    $dir = 'public://drimage';
    \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY);
    \Drupal::service('file_system')->saveData(file_get_contents($source->uri), 'public://drimage/test.png', FileExists::Replace);
    File::create(['uri' => 'public://drimage/test.png', 'status' => 1])->save();
  }

  /**
   * Dispatches a style request through the Drimage subscriber.
   */
  protected function request(string $style, string $file): RequestEvent {
    $dir = \Drupal::service('stream_wrapper_manager')->getViaScheme('public')->getDirectoryPath();
    $request = Request::create('/' . $dir . '/styles/' . $style . '/public/' . $file);
    $event = new RequestEvent($this->container->get('http_kernel'), $request, HttpKernelInterface::MAIN_REQUEST);
    $this->container->get('drimage_improved.event_subscriber')->onKernelRequest($event);
    return $event;
  }

  /**
   * A focal-point style generates with image_widget_crop installed.
   */
  public function testFocalStyleGeneratesWithImageWidgetCropInstalled(): void {
    $response = $this->request('drimage_improved_focal_320_0', 'drimage/test.png')->getResponse();
    $this->assertNotNull($response);
    $this->assertSame(200, $response->getStatusCode());
    $this->assertStringStartsWith('image/', (string) $response->headers->get('Content-Type'));
  }

  /**
   * A focal-point style with a height generates too.
   */
  public function testFocalStyleWithHeightGenerates(): void {
    $response = $this->request('drimage_improved_focal_320_240', 'drimage/test.png')->getResponse();
    $this->assertNotNull($response);
    $this->assertSame(200, $response->getStatusCode());
  }

  /**
   * A missing source still resolves to a clean 404.
   */
  public function testMissingSourceReturns404(): void {
    $response = $this->request('drimage_improved_focal_320_0', 'drimage/missing.png')->getResponse();
    $this->assertNotNull($response);
    $this->assertSame(404, $response->getStatusCode());
  }

  /**
   * A file name containing a plus symbol still resolves.
   *
   * The urldecode() call turned "+" into a space, so the file lookup missed and no
   * derivative was produced for names like "my+file.png" (issue #3545688).
   */
  public function testPlusInFileNameResolves(): void {
    $source = current($this->getTestFiles('image'));
    \Drupal::service('file_system')->saveData(file_get_contents($source->uri), 'public://drimage/my+file.png', FileExists::Replace);
    File::create(['uri' => 'public://drimage/my+file.png', 'status' => 1])->save();

    $response = $this->request('drimage_improved_focal_320_0', 'drimage/my+file.png')->getResponse();
    $this->assertNotNull($response);
    $this->assertSame(200, $response->getStatusCode());
  }

}
