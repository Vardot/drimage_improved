<?php

declare(strict_types=1);

namespace Drupal\Tests\drimage_improved\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Tests the Stage File Proxy integration.
 *
 * The subscriber runs the inbound path processors to strip language prefixes.
 * Those processors write to the request they are given: core's file processor
 * sets a "file" query parameter, so passing the live request breaks routing for
 * private file requests (issue #3551332).
 *
 * @group drimage_improved
 */
class DrimageStageFileProxyTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'file', 'image', 'stage_file_proxy', 'drimage_improved'];

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
   * Dispatches a request through the decorating subscriber.
   */
  protected function dispatch(Request $request): void {
    $subscriber = $this->container->get('drimage_improved.stage_file_proxy.proxy_subscriber');
    $subscriber->checkFileOrigin(new RequestEvent($this->container->get('http_kernel'), $request, HttpKernelInterface::MAIN_REQUEST));
  }

  /**
   * A private file request keeps its query parameters.
   */
  public function testPrivateFileRequestIsNotModified(): void {
    $request = Request::create('/system/files/2026-01/private.txt');
    $this->dispatch($request);

    $this->assertFalse($request->query->has('file'), 'The subscriber leaves the request untouched.');
    $this->assertSame('/system/files/2026-01/private.txt', $request->getRequestUri());
  }

  /**
   * A Drimage request for an unknown file is handed on untouched.
   */
  public function testUnknownDrimageFileIsHandedOn(): void {
    $request = Request::create('/drimage_improved/320/0/999999/-/example.png');
    $this->dispatch($request);

    $this->assertSame('/drimage_improved/320/0/999999/-/example.png', $request->getRequestUri());
  }

}
