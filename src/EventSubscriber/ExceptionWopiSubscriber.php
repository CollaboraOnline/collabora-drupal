<?php

declare(strict_types=1);

namespace Drupal\collabora_online\EventSubscriber;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\EventSubscriber\HttpExceptionSubscriberBase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

/**
 * Subscriber to format WOPI failure responses.
 */
class ExceptionWopiSubscriber extends HttpExceptionSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function getHandledFormats() {
    return ['collabora_online_wopi'];
  }

  /**
   * {@inheritdoc}
   */
  protected static function getPriority() {
    return -175;
  }

  /**
   * Handles all 4xx errors.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The event to process.
   */
  public function on4xx(ExceptionEvent $event) {
    /** @var \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception */
    $exception = $event->getThrowable();
    $content = $exception->getMessage();
    $code = $exception->getStatusCode();
    $headers = $exception->getHeaders();

    if ($code === 404) {
      if (str_starts_with($content, 'The "media" parameter was not converted')) {
        // The existing message reveals more detail than needed.
        $content = 'Media not found.';
      }
    }

    // If the exception is cacheable, generate a cacheable response.
    if ($exception instanceof CacheableDependencyInterface) {
      $response = new CacheableResponse($content, $code, $headers);
      $response->addCacheableDependency($exception);
    }
    else {
      $response = new Response($content, $code, $headers);
    }

    $response->headers->set('Content-Type', 'text/plain');

    $event->setResponse($response);
  }

}
