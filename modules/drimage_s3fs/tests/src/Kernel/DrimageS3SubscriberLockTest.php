<?php

declare(strict_types=1);

namespace Drupal\Tests\drimage_s3fs\Kernel;

use Drupal\drimage_improved\DrimageManagerInterface;
use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Tests the S3 Drimage subscriber while a derivative is being generated.
 *
 * The Drimage manager waits for a derivative another request is generating
 * and throws "Image generation in progress" if it is still not ready. The S3
 * subscriber used to catch only NotFoundHttpException, so that exception
 * escaped as an error. The manager is replaced by a stub, so no S3 bucket is
 * needed.
 *
 * @group drimage_improved
 */
class DrimageS3SubscriberLockTest extends KernelTestBase {

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
    // A public:// file served through the S3 path, as with the s3fs public
    // takeover, so nothing talks to S3.
    File::create(['uri' => 'public://drimage/test.png', 'status' => 1])->save();
  }

  /**
   * Replaces the Drimage manager with one that answers with the given outcome.
   */
  protected function manager(?\Throwable $exception): object {
    $manager = new class($exception) implements DrimageManagerInterface {

      /**
       * How many calls have been made.
       */
      public int $calls = 0;

      public function __construct(private readonly ?\Throwable $exception) {}

      /**
       * Throws the outcome, or delivers an image.
       */
      public function image(Request $request, int $width, int $height, int $fid, $iwc_id = NULL, $format = NULL): Response {
        $this->calls++;
        if ($this->exception) {
          throw $this->exception;
        }
        return new Response('image', 200, ['Content-Type' => 'image/png']);
      }

    };
    $this->container->set('drimage_improved.manager', $manager);
    return $manager;
  }

  /**
   * Dispatches an S3 style request through the S3 Drimage subscriber.
   */
  protected function request(): ?Response {
    $request = Request::create('/s3/files/styles/drimage_improved_320_0/public/drimage/test.png');
    $event = new RequestEvent($this->container->get('http_kernel'), $request, HttpKernelInterface::MAIN_REQUEST);
    $this->container->get('drimage_s3fs.event_subscriber')->onKernelRequest($event);
    return $event->getResponse();
  }

  /**
   * Still being generated after the wait: a plain 503, not an escaped error.
   */
  public function testStillGeneratingReturns503Response(): void {
    $manager = $this->manager(new ServiceUnavailableHttpException(3, 'Image generation in progress. Try again shortly.'));
    $response = $this->request();
    $this->assertNotNull($response);
    $this->assertSame(503, $response->getStatusCode());
    $this->assertSame('3', $response->headers->get('Retry-After'));
    $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    $this->assertSame(1, $manager->calls);
  }

  /**
   * A generated derivative is delivered.
   */
  public function testDerivativeIsDelivered(): void {
    $this->manager(NULL);
    $response = $this->request();
    $this->assertNotNull($response);
    $this->assertSame(200, $response->getStatusCode());
  }

  /**
   * A source the manager cannot find still answers a clean 404.
   */
  public function testMissingSourceReturns404Response(): void {
    $this->manager(new NotFoundHttpException('Error generating image, file not found.'));
    $response = $this->request();
    $this->assertNotNull($response);
    $this->assertSame(404, $response->getStatusCode());
  }

}
