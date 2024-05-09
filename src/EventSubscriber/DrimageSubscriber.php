<?php

declare(strict_types=1);

namespace Drupal\drimage_improved\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sunscribes to kernel request event so it handles non-exixting images.
 */
final class DrimageSubscriber implements EventSubscriberInterface {

  /**
   * The stream wrapper manager service.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new DrimageRoutes object.
   *
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager
   *   The stream wrapper manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(StreamWrapperManagerInterface $stream_wrapper_manager, EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler, ConfigFactoryInterface $config_factory) {
    $this->streamWrapperManager = $stream_wrapper_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('stream_wrapper_manager'),
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('config.factory')
    );
  }

  /**
   * Kernel request event handler.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *  The request event.
   */
  public function onKernelRequest(RequestEvent $event): void {
    // Check if the request contains with /styles/drimage_improved_.
    $path = $event->getRequest()->getPathInfo();
    if (strpos($path, '/styles/drimage_improved_')) {
      $config = $this->configFactory->get('drimage_improved.settings');
      $directory_path = $this->streamWrapperManager->getViaScheme('public')->getDirectoryPath();
      // Remove the directory path from the path.
      $path = str_replace('/' . $directory_path, '', $path);
      $parts = explode('/', $path);
      $style = $parts[2];
      // Split style and get width and height.
      $style_parts = explode('_', $style);
      $scheme = $parts[3];
      $iwc_id = '-';
      if ($this->moduleHandler->moduleExists('image_widget_crop')) {
        $width = $style_parts[2];
        $height = $style_parts[3];
        $iwc_id = $style_parts[4];
      }
      elseif ($this->moduleHandler->moduleExists('focal_point')) {
        $width = $style_parts[3];
        $height = $style_parts[4];
      }
      else {
        $width = $style_parts[2];
        $height = $style_parts[3];
      }

      // Get the file path.
      $file_path = substr($path, strpos($path, $scheme) + strlen($scheme) + 1);
      $file_name = $file_path;
      if ($config->get('imageapi_optimize_webp') || $config->get('core_webp')) {
        // Remove the extrap .webp in the filename.
        $file_name = substr($file_name, 0, (strrpos($file_name, '.')));
      }
      // dd($file_name);
      // Redirect to drimage_improved.image route.
      $image = $this->entityTypeManager->getStorage('file')->loadByProperties(['uri' => $scheme . '://' . urldecode($file_name)]);
      $filename = end($parts);
      $filename_parts = explode('.', $filename);
      $format = end($filename_parts);
      // Create url from drimage_improved.image route.
      $url = Url::fromRoute('drimage_improved.image', [
        'width' => $width,
        'height' => $height,
        'fid' => end($image)->id(),
        'iwc_id' => $iwc_id,
        'format' => $format,
      ]);
      $response = new TrustedRedirectResponse($url->setAbsolute()->toString());
      $response->send();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::REQUEST => ['onKernelRequest'],
    ];
  }

}
