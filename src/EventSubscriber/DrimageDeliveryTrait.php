<?php

declare(strict_types=1);

namespace Drupal\drimage_improved\EventSubscriber;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * Delivers a Drimage derivative and answers cleanly when it cannot.
 *
 * Shared by the Drimage subscribers (local files and S3), so both answer the
 * same way. Expects a $drimageManager property holding the Drimage manager,
 * which waits for a derivative another request is generating (see
 * DrimageManager::deliverOnce()).
 */
trait DrimageDeliveryTrait {

  /**
   * Sets the derivative response on the event.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event.
   * @param int $width
   *   The requested width.
   * @param int $height
   *   The requested height.
   * @param int $fid
   *   The source file ID.
   * @param string|null $iwc_id
   *   The image_widget_crop crop type, or "-" for none.
   * @param string|null $format
   *   The requested format (extension).
   *
   * @return bool
   *   TRUE when the image was delivered, FALSE when the response is a 404 or a
   *   503.
   */
  protected function deliverDrimage(RequestEvent $event, int $width, int $height, int $fid, $iwc_id, $format): bool {
    try {
      $event->setResponse($this->drimageManager->image($event->getRequest(), $width, $height, $fid, $iwc_id, $format));
      return TRUE;
    }
    catch (NotFoundHttpException $exception) {
      // Force Drupal to stop processing the request.
      $event->setResponse(new Response($exception->getMessage(), 404));
      return FALSE;
    }
    catch (ServiceUnavailableHttpException $exception) {
      // Still being generated after the wait: a plain 503 that no cache keeps,
      // instead of an uncaught exception that is logged as an error.
      $event->setResponse(new Response($exception->getMessage(), 503, [
        'Retry-After' => '3',
        'Cache-Control' => 'no-store',
      ]));
      return FALSE;
    }
  }

}
