<?php

declare(strict_types=1);

namespace Drupal\Tests\drimage_improved\Kernel;

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\drimage_improved\DrimageManager;
use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\TestFileCreationTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Tests the Drimage subscriber while a derivative is being generated.
 *
 * A request for a derivative that another request is generating waits for
 * that request's lock, then delivers the image; if it is still being
 * generated after the wait, the subscriber answers a plain 503 instead of
 * letting core's ServiceUnavailableHttpException escape.
 *
 * @group drimage_improved
 */
class DrimageSubscriberLockTest extends KernelTestBase {

  use TestFileCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'file', 'image', 'crop', 'focal_point', 'drimage_improved',
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
    $this->installConfig(['system', 'image', 'focal_point', 'drimage_improved']);
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
   * A derivative still being generated after the wait answers a plain 503.
   */
  public function testLockedDerivativeReturns503Response(): void {
    // Kernel tests use a null lock backend, which never blocks, so this mock
    // plays the other request: it holds the Drimage lock the whole time.
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturnCallback(
      static fn (string $name): bool => !str_starts_with($name, 'drimage_improved_deliver:'),
    );
    $lock->expects($this->atLeastOnce())
      ->method('wait')
      ->with($this->stringStartsWith('drimage_improved_deliver:'))
      ->willReturnCallback(static function (): bool {
        // Still held after a while, as LockBackendInterface::wait() reports.
        usleep(250000);
        return TRUE;
      });
    $lock->expects($this->never())->method('release');
    $this->container->set('lock', $lock);

    $started = microtime(TRUE);
    $response = $this->request('drimage_improved_focal_320_0', 'drimage/test.png')->getResponse();
    $this->assertNotNull($response);
    $this->assertSame(503, $response->getStatusCode());
    $this->assertSame('3', $response->headers->get('Retry-After'));
    $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    $this->assertGreaterThanOrEqual(DrimageManager::GENERATION_WAIT, microtime(TRUE) - $started);
  }

  /**
   * A waiting request takes the lock in turn and delivers the image.
   */
  public function testWaitingRequestDeliversTheImage(): void {
    $drimage_attempts = 0;
    $released = [];
    $lock = $this->createMock(LockBackendInterface::class);
    // The other request holds the Drimage lock on the first try only.
    $lock->method('acquire')->willReturnCallback(
      static function (string $name) use (&$drimage_attempts): bool {
        return !str_starts_with($name, 'drimage_improved_deliver:') || ++$drimage_attempts > 1;
      },
    );
    $lock->expects($this->once())->method('wait')->willReturn(FALSE);
    $lock->method('release')->willReturnCallback(function (string $name) use (&$released): void {
      $released[] = $name;
    });
    $this->container->set('lock', $lock);

    $response = $this->request('drimage_improved_focal_320_0', 'drimage/test.png')->getResponse();
    $this->assertNotNull($response);
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame(2, $drimage_attempts);
    $this->assertCount(2, $released);
  }

  /**
   * The first request takes the Drimage lock and releases it once delivered.
   */
  public function testFirstRequestTakesAndReleasesTheLock(): void {
    $released = [];
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(TRUE);
    $lock->expects($this->never())->method('wait');
    $lock->method('release')->willReturnCallback(function (string $name) use (&$released): void {
      $released[] = $name;
    });
    $this->container->set('lock', $lock);

    $response = $this->request('drimage_improved_focal_320_0', 'drimage/test.png')->getResponse();
    $this->assertNotNull($response);
    $this->assertSame(200, $response->getStatusCode());
    $this->assertCount(2, $released);
    $this->assertStringStartsWith('image_style_deliver:', $released[0]);
    $this->assertStringStartsWith('drimage_improved_deliver:', $released[1]);
  }

}
