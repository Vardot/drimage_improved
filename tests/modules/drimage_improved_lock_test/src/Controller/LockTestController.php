<?php

declare(strict_types=1);

namespace Drupal\drimage_improved_lock_test\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Lock\DatabaseLockBackend;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\ProxyClass\Lock\DatabaseLockBackend as DatabaseLockBackendProxy;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\drimage_improved\DrimageManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Holds the generation lock of a Drimage derivative, as another request would.
 *
 * A Drimage request holds a lock (DrimageManager::generationLockName()) while
 * it generates a derivative; any other request for the same derivative waits
 * for it, and answers "Image generation in progress" if it is still held. A
 * lock taken through the lock service is released at the end of the request
 * that took it, so this writes the row into the semaphore table directly: the
 * lock then outlives this request and expires on its own after the requested
 * number of seconds, exactly like a slow generation in another PHP process.
 *
 * Testing only: the module is hidden and lives under tests/.
 */
final class LockTestController implements ContainerInjectionInterface {

  /**
   * The value written into the semaphore rows this controller owns.
   */
  private const LOCK_OWNER = 'drimage_improved_lock_test';

  public function __construct(
    private readonly Connection $database,
    private readonly LockBackendInterface $lock,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly StreamWrapperManagerInterface $streamWrapperManager,
    private readonly FileSystemInterface $fileSystem,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly DrimageManager $drimageManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('lock'),
      $container->get('entity_type.manager'),
      $container->get('stream_wrapper_manager'),
      $container->get('file_system'),
      $container->get('config.factory'),
      $container->get('drimage_improved.manager'),
    );
  }

  /**
   * Removes a derivative and holds its generation lock for a while.
   *
   * Query parameters:
   * - uri: the source image URI, a managed file (public://...).
   * - width: the derivative width; defaults to the smallest valid width.
   * - seconds: how long the lock is held, at most 60. The lock expires on its
   *   own, like an abandoned one. 0 removes the derivative and any lock this
   *   module still holds on it.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The derivative URL and the lock that is held.
   */
  public function hold(Request $request): JsonResponse {
    // The lock service is usually the lazy proxy of the database backend.
    if (!$this->lock instanceof DatabaseLockBackend && !$this->lock instanceof DatabaseLockBackendProxy) {
      return $this->json(['error' => 'The lock service is not the database lock backend.'], 501);
    }

    $uri = (string) $request->query->get('uri', '');
    if (!str_starts_with($uri, 'public://') || str_contains($uri, '..')) {
      return $this->json(['error' => 'The uri must be a public:// file.'], 400);
    }
    if (!$this->entityTypeManager->getStorage('file')->loadByProperties(['uri' => $uri])) {
      return $this->json(['error' => 'No managed file has that uri.'], 404);
    }

    $settings = $this->configFactory->get('drimage_improved.settings');
    $width = (int) $request->query->get('width', (int) $settings->get('upscale'));
    if (!$this->drimageManager->checkRequestedDimensions($width, 0)) {
      return $this->json(['error' => 'That width is not one Drimage serves.'], 400);
    }
    $seconds = max(0.0, min(60.0, (float) $request->query->get('seconds', '1')));

    // The style the Drimage subscriber resolves for this width.
    $image_style = $this->drimageManager->findImageStyle([$width, 0]);
    if (!$image_style) {
      return $this->json(['error' => 'No image style for that width.'], 500);
    }

    // Remove the derivative (and its WebP twin) so the next request has to
    // generate it, which is the only time core takes the lock.
    foreach ([$uri, $uri . '.webp'] as $source) {
      $derivative = $image_style->buildUri($source);
      if (file_exists($derivative)) {
        $this->fileSystem->delete($derivative);
      }
    }

    // The lock a Drimage request holds while it generates this derivative.
    $lock_name = DrimageManager::generationLockName($image_style, $uri);
    $expire = microtime(TRUE) + $seconds;
    if ($seconds > 0) {
      $this->database->merge('semaphore')
        ->keys(['name' => $lock_name])
        ->fields(['value' => self::LOCK_OWNER, 'expire' => $expire])
        ->execute();
    }
    else {
      // Nobody is generating it: drop a lock an earlier hold left behind.
      $this->database->delete('semaphore')
        ->condition('name', $lock_name)
        ->condition('value', self::LOCK_OWNER)
        ->execute();
    }

    $directory = $this->streamWrapperManager->getViaScheme('public')->getDirectoryPath();
    $target = $this->streamWrapperManager->getTarget($uri);
    return $this->json([
      'style' => $image_style->id(),
      'url' => base_path() . $directory . '/styles/' . $image_style->id() . '/public/' . $target,
      'lock' => $seconds > 0 ? $lock_name : NULL,
      'expire' => $seconds > 0 ? $expire : NULL,
    ]);
  }

  /**
   * A JSON response that no cache keeps.
   */
  private function json(array $data, int $status = 200): JsonResponse {
    return new JsonResponse($data, $status, ['Cache-Control' => 'no-store']);
  }

}
