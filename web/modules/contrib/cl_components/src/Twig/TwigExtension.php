<?php

namespace Drupal\cl_components\Twig;

use Drupal\cl_components\ComponentPluginManager;
use Drupal\cl_components\Exception\TemplateNotFoundException;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * The twig extension so Drupal can recognize the new code.
 */
class TwigExtension extends AbstractExtension {

  /**
   * Creates TwigExtension.
   *
   * @param \Drupal\cl_components\ComponentPluginManager $pluginManager
   *   The plugin manager.
   */
  public function __construct(private readonly ComponentPluginManager $pluginManager) {}

  /**
   * {@inheritdoc}
   */
  public function getFunctions() {
    return [
      new TwigFunction(
        'cl_components_additional_context',
        [$this, 'addAdditionalContext'],
        ['needs_context' => TRUE]
      ),
    ];
  }

  /**
   * Appends additional context to the template based on the template id.
   *
   * @param array &$context
   *   The context.
   * @param string $component_id
   *   The component ID.
   * @param string $variant
   *   The variant.
   *
   * @throws \Drupal\cl_components\Exception\TemplateNotFoundException
   * @throws \Drupal\cl_components\Exception\ComponentNotFoundException
   */
  public function addAdditionalContext(array &$context, string $component_id, string $variant, int $debug) {
    $component = $this->pluginManager->find($component_id);
    if (!empty($variant) && !in_array($variant, $component->getVariants(), TRUE)) {
      $message = sprintf(
        'Unable to render variant "%s". This variant is not declared in the %s.component.yml for this component.',
        $variant,
        $component_id
      );
      throw new TemplateNotFoundException($message);
    }
    $context = array_merge(
      $context,
      ['debugMode' => (bool) $debug],
      $component->additionalRenderContext($variant)
    );
  }

}
