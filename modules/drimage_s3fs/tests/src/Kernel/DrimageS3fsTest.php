<?php

declare(strict_types=1);

namespace Drupal\Tests\drimage_s3fs\Kernel;

use Drupal\drimage_s3fs\Hook\DrimageS3fsHooks;
use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Tests the Drimage S3fs integration: services, plugins and the S3 style path.
 *
 * @group drimage_improved
 */
class DrimageS3fsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'file', 'image', 's3fs', 'drimage_improved', 'drimage_s3fs'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installConfig(['drimage_improved']);
  }

  /**
   * The S3 request subscriber is registered and listens to kernel requests.
   */
  public function testSubscriberRegistered(): void {
    $this->assertTrue($this->container->has('drimage_s3fs.event_subscriber'));
    $events = $this->container->get('drimage_s3fs.event_subscriber')::getSubscribedEvents();
    $this->assertArrayHasKey(KernelEvents::REQUEST, $events);
  }

  /**
   * The S3 formatter and its theme hook are available.
   */
  public function testFormatterAndTheme(): void {
    $this->assertTrue($this->container->get('plugin.manager.field.formatter')->hasDefinition('drimage_s3fs'));
    $this->assertArrayHasKey('drimage_s3_formatter', \Drupal::service(DrimageS3fsHooks::class)->theme());
  }

  /**
   * A focal S3 style request for a missing source resolves to a clean 404.
   *
   * The S3 subscriber parses the width and height out of the style name
   * before it looks up the source file; an unknown file must end in a 404,
   * never a PHP warning.
   */
  public function testFocalStylePathReturns404ForMissingSource(): void {
    $this->container->get('module_installer')->install(['focal_point']);
    foreach (['drimage_improved_focal_320_240', 'drimage_improved_focal_320_0'] as $style) {
      $request = Request::create('/s3/files/styles/' . $style . '/s3/no-such-file.png');
      $event = new RequestEvent($this->container->get('http_kernel'), $request, HttpKernelInterface::MAIN_REQUEST);
      $this->container->get('drimage_s3fs.event_subscriber')->onKernelRequest($event);
      $this->assertNotNull($event->getResponse(), $style);
      $this->assertSame(404, $event->getResponse()->getStatusCode(), $style);
    }
  }

}
