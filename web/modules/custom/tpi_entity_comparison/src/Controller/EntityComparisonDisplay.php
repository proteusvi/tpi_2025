<?php

namespace Drupal\tpi_entity_comparison\Controller;

use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\entity_comparison\Controller\EntityComparisonController;

/**
 * The comparison page controller.
 */
class EntityComparisonDisplay extends EntityComparisonController {

  /**
   * Add places vacantes in second position betwen Pretataire name and Options.
   */
  const POSITION_IN_LIST = 2;

  /**
   * Compare page.
   *
   * @param int $_entity_comparison_id
   *   Entity comparission ID.
   *
   * @return array
   *   Array to render table for the current page.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function compare($_entity_comparison_id = NULL) {
    $build = parent::compare($_entity_comparison_id);

    $nodeIdsList = $this->buildNodeIdsListFromHeader($build['#header']);
    if (empty($nodeIdsList)) {
      // Empty compare list, nothing to do.
      return $build;
    }

    $node = $this->entityTypeManager->getStorage('node')->load($nodeIdsList[0]);
    if ($node->bundle() == 'recipe') {
      // In case of Recipe compare, add Places vacantes related to MMT.
      $recipeRenderedList = $this->buildRecipeRenderedList($nodeIdsList);
      array_splice($build['#rows'], self::POSITION_IN_LIST, 0, [$recipeRenderedList]);
    }

    // Reformating render table to add css classes.
    foreach ($build['#rows'] as $key => $row) {
      $itemsCount = count($build['#rows'][$key]);
      $build['#rows'][$key][0] = ['data' => $build['#rows'][$key][0], 'class' => 'w-10'];

      for ($i = 1; $i < $itemsCount; $i++) {
        // First col take 10% so the others cols have to spare 90% of the width.
        $w = 'w-' . abs(90 / ($itemsCount - 1));
        $build['#rows'][$key][$i] = ['data' => $build['#rows'][$key][$i], 'class' => $w];
      }
    }

    // Take the referer url and compute back link.
    $referer = \Drupal::request()->headers->get('referer');
    if (!empty($referer)) {
      $returnTo = new Link("Retour à la page précédente", Url::fromUri($referer));
      $build["#prefix"] .= $returnTo->toString();
    }

    // Remove first line which contains remove from the list link.
    array_shift($build['#rows']);

    return $build;
  }

  /**
   * Build a list of nodes to compare.
   */
  protected function buildNodeIdsListFromHeader(array $header): array {
    $nodeIdsList = [];
    foreach ($header as $data) {
      if (is_array($data)) {
        /** @var Drupal\Core\Url $url */
        $url = $data['data']['#url'];
        $nodeIdsList[] = $url->getRouteParameters()['node'];
      }
    }
    return $nodeIdsList;
  }

  /**
   * Render the places vacantes block.
   *
   * If no place vacante, return an empty string.
   */
  protected function renderRecipe(string $nid):Markup|string {
    $build = [];

    $block_manager = \Drupal::service('plugin.manager.block');
    // You can hard code configuration or you load from settings.
    $config = [];
    $plugin_block = $block_manager->createInstance('edg_mmt_places_vacantes', $config);
    $build = $plugin_block->build(['nid' => $nid]);
    $render = $this->renderer->render($build);

    return $render;
  }

  /**
   * Build render list recipe.
   */
  protected function buildRecipeRenderedList(array $nids): array {
    $recipeRenderedList = [];
    $recipeRenderedList[] = 'recipe';
    foreach ($nids as $nid) {
      $recipeRenderedList[] = $this->renderrecipe($nid);
    }

    return $recipeRenderedList;
  }

}
