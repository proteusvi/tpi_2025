<?php

namespace Drupal\cl_block\Render;

use Drupal\cl_components\ComponentPluginManager;
use Drupal\cl_components\Exception\ComponentNotFoundException;
use Drupal\cl_components\Plugin\Component;
use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Utility\Token;

/**
 * Renders blocks for components.
 */
class ComponentBlockRenderer {

  /**
   * Constructs ComponentBlockRenderer classes.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   * @param \Drupal\cl_components\ComponentPluginManager $pluginManager
   *   The component plugin manager.
   */
  public function __construct(
    protected readonly RendererInterface $renderer,
    protected readonly Token $token,
    protected readonly ComponentPluginManager $pluginManager
  ) {}

  /**
   * Builds a component with all the necessary context for a block.
   *
   * @param string $component_id
   *   The component ID.
   * @param string $variant
   *   The component variant.
   * @param array $context
   *   The Twig context.
   * @param array $twig_blocks
   *   The content for the twig blocks.
   * @param array $token_data
   *   The token data for token replacements.
   *
   * @return array
   *   The output render array.
   */
  public function buildFromId(
    string $component_id,
    string $variant,
    array $context,
    array $twig_blocks,
    array $token_data = []
  ): array {
    try {
      $component = $this->pluginManager->find($component_id);
    }
    catch (ComponentNotFoundException $e) {
      return ['#markup' => ''];
    }
    return $this->build(
      $component,
      $variant,
      $context,
      $twig_blocks,
      $token_data
    );
  }

  /**
   * Renders a component with all the necessary context for a block.
   *
   * @param \Drupal\cl_components\Plugin\Component $component
   *   The component.
   * @param string $variant
   *   The component variant.
   * @param array $context
   *   The Twig context.
   * @param array $twig_blocks
   *   The content for the twig blocks.
   * @param array $token_data
   *   The token data for token replacements.
   *
   * @return array
   *   The output render array.
   */
  public function build(
    Component $component,
    string $variant,
    array $context,
    array $twig_blocks,
    array $token_data = []
  ): array {
    $token_options = ['clear' => TRUE];
    try {
      // If there is a language in the context of the block, use it.
      $language = $token_data['language'] ?? NULL;
      if ($language instanceof LanguageInterface) {
        $token_options['langcode'] = $language->getId();
      }
    }
    catch (ContextException $e) {
      // Intentionally left blank.
    }
    $context_alter_callback = fn(mixed $data, BubbleableMetadata $bubbleable_metadata) => $this
      ->replaceTokensRecursively(
        $data,
        $token_data,
        $token_options,
        $bubbleable_metadata,
        'plain'
      );
    $blocks_alter_callback = fn(mixed $data, BubbleableMetadata $bubbleable_metadata) => $this
      ->token
      ->replace($data['value'], $token_data, $token_options, $bubbleable_metadata);
    return [
      '#type' => 'cl_component',
      '#component' => $component->getId(),
      '#variant' => $variant,
      '#twig_blocks' => $twig_blocks,
      '#context' => $context,
      '#context_alter_callback' => $context_alter_callback,
      '#blocks_alter_callback' => $blocks_alter_callback,
    ];
  }

  /**
   * Renders a component.
   */
  public function renderComponentFromId(
    string $component_id,
    string $variant,
    array $context,
    array $twig_blocks,
    array $token_data,
  ): MarkupInterface {
    $build = $this->buildFromId(
      $component_id,
      $variant,
      $context,
      $twig_blocks,
      $token_data,
    );
    try {
      return $this->renderer->render($build);
    }
    catch (\Exception $e) {
      return Markup::create('');
    }
  }

  /**
   * Bubbles the cacheability metadata.
   *
   * This is useful when we manually serialize the render array for DOM
   * manipulation. This happens, at least, in the WYSIWYG replacements.
   *
   * @param \Drupal\Core\Render\BubbleableMetadata $metadata
   *   The metadata to bubble.
   */
  public function bubbleMetadata(BubbleableMetadata $metadata): void {
    $lib_build = [];
    $metadata->applyTo($lib_build);
    try {
      $this->renderer->render($lib_build);
    }
    catch (\Exception $e) {
      watchdog_exception('cl_block', $e);
    }
  }

  /**
   * Replaces tokens recursively in a data structure.
   *
   * @param array $context
   *   The context.
   * @param array $token_data
   *   The token data.
   * @param \Drupal\Core\Render\BubbleableMetadata $bubbleable_metadata
   *   The bubbleable metadata.
   * @param string $replacement_type
   *   Either 'plain' or 'html' to force replacing as plain text or not.
   *
   * @return array
   *   The context with the tokens replaced.
   */
  private function replaceTokensRecursively(array $context, array $token_data, array $token_options, BubbleableMetadata $bubbleable_metadata, string $replacement_type = 'plain'): array {
    return array_map(
      function (mixed $item) use ($token_data, $token_options, $bubbleable_metadata, $replacement_type): mixed {
        if (is_string($item)) {
          $replacement_args = [
            $item,
            $token_data,
            $token_options,
            $bubbleable_metadata,
          ];
          return $replacement_type === 'plain'
            ? call_user_func_array([
              $this->token,
              'replacePlain',
            ], $replacement_args)
            : call_user_func_array([
              $this->token,
              'replace',
            ], $replacement_args);
        }
        if (is_array($item)) {
          return $this->replaceTokensRecursively($item, $token_data, $token_options, $bubbleable_metadata, $replacement_type);
        }
        return $item;
      },
      $context
    );
  }

}
