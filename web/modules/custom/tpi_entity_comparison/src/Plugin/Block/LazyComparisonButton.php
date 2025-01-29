<?php

namespace Drupal\tpi_entity_comparison\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provide a block to display the button and its state.
 *
 * @Block(
 *   id = "tpi_entity_comparison_button",
 *   admin_label = @Translation("Zeteo Comparison Button")
 * )
 */
class LazyComparisonButton extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build($param = []) {
    if (!isset($param['entity_id'])) {
      return [];
    }

    $build['lazy_comparison_list'] = [
      '#lazy_builder' => [
        'tpi_entity_comparison.lazy_comparison_button_service:renderComparisonButton',
        [
          'entity_id' => $param['entity_id'],
          'entity_comparison_id' => $param['entity_comparison_id'],
        ],
      ],
      '#create_placeholder' => TRUE,
    ];

    return $build;
  }

}
