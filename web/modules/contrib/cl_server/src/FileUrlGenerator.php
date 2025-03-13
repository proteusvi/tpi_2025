<?php

namespace Drupal\cl_server;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Generates files always as absolute URLs.
 */
class FileUrlGenerator implements FileUrlGeneratorInterface {

  use DependencySerializationTrait;

  /**
   * The file generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  private $fileGenerator;

  /**
   * The request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  private $request;

  /**
   * Constructs a file generator decorator.
   *
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $fileGenerator
   *   The file generator we are decorating.
   * @param \Drupal\Core\Http\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(FileUrlGeneratorInterface $fileGenerator, RequestStack $request_stack) {
    $this->fileGenerator = $fileGenerator;
    $this->request = $request_stack->getCurrentRequest();
  }

  /**
   * {@inheritdoc}
   */
  public function generateString(string $uri): string {
    // This is the only reason to decorate this service. We want all file URLs
    // to be absolute withing the Storybook iframe.
    return Util::isRenderController($this->request)
      ? $this->fileGenerator->generateAbsoluteString($uri)
      : $this->fileGenerator->generateString($uri);
  }

  /**
   * {@inheritdoc}
   */
  public function generateAbsoluteString(string $uri): string {
    return $this->fileGenerator->generateAbsoluteString($uri);
  }

  /**
   * {@inheritdoc}
   */
  public function generate(string $uri): Url {
    return $this->fileGenerator->generate($uri);
  }

  /**
   * {@inheritdoc}
   */
  public function transformRelative(string $file_url, bool $root_relative = TRUE): string {
    return $this->fileGenerator->transformRelative($file_url, $root_relative);
  }

}
