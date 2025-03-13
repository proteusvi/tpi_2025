<?php

namespace Drupal\cl_server\EventSubscriber;

use Drupal\cl_server\Util;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Removes the X-Frame-Options header for the player embed route.
 *
 * Core adds an X-Frame-Options: SAMEORIGIN header to all responses. For the
 * render controller, we need to remove this header so the browser will allow
 * the response to be rendered in an iframe.
 *
 * @see \Drupal\Core\EventSubscriber\FinishResponseSubscriber::onRespond
 * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/X-Frame-Options
 */
class RemoveXFrameOptionsSubscriber implements EventSubscriberInterface {

  /**
   * Remove the X-Frame-Options header from the response for our route.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The event to process.
   */
  public function removeFrameOptions(ResponseEvent $event) {
    if (Util::isRenderController($event->getRequest())) {
      $response = $event->getResponse();
      $response->headers->remove('X-Frame-Options');
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE][] = ['removeFrameOptions', -10];
    return $events;
  }

}
