<?php

/**
 * @file
 * Preprocessors and helper functions to make theming easier.
 */

use Drupal\entity_comparison\Entity\EntityComparison;

/**
 * Prepares variables for entity_comparison_link templates.
 *
 * Default template: entity-comparison-link.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - entity_comparison: Machine name of the entity comparison.
 *   - id: Entity ID to compare.
 */
function template_preprocess_entity_comparison_link(array &$variables) {
  // Get the corresponding entity comparison entity configuration.
  $entity_comparison_id = $variables['entity_comparison'];
  if (!$entity_comparison_id || !$variables['id']) {
    return;
  }

  $entity_comparison = EntityComparison::load($entity_comparison_id);
  if (!$entity_comparison) {
    return;
  }

  /**
   * @var \Drupal\Core\Link $link
   */
  $link = $entity_comparison->getLink($variables['id'], TRUE);
  $variables['output'] = $link->toRenderable();
}
