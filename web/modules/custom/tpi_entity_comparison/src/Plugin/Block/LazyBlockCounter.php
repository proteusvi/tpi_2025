<?php

declare(strict_types=1);

namespace Drupal\tpi_entity_comparison\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides a lazy block counter block.
 *
 * @Block(
 *   id = "tpi_entity_comparison_lazy_block_counter",
 *   admin_label = @Translation("Lazy block counter"),
 *   category = @Translation("Custom"),
 * )
 */
final class LazyBlockCounter extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {

    $build['lazy_comparison_counter'] = [
      '#lazy_builder' => ['tpi_entity_comparison.lazy_counter_items_service:renderComparisonCounter', []],
      '#create_placeholder' => TRUE,
    ];
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account): AccessResult {
    // @todo Evaluate the access condition here.
    return AccessResult::allowedIf(TRUE);
  }

}
