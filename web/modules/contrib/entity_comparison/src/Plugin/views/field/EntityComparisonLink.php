<?php

namespace Drupal\entity_comparison\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;
use Drupal\views\Plugin\views\display\DisplayPluginBase;

/**
 * Field handler to display entity comparison link.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("entity_comparison_link")
 */
class EntityComparisonLink extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);
    if (!isset($this->options['enitity_comparison'])) {
      $this->options['enitity_comparison'] = preg_replace('/^entity_comparison_link_/', '', $this->options['id']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account) {
    return $account->hasPermission("use " . $this->options['enitity_comparison'] . " entity comparison");
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $entity_id = $this->getValue($values);
    $enitity_comparison = $this->options['enitity_comparison'];

    return [
      '#theme' => 'entity_comparison_link',
      '#id' => $entity_id,
      '#entity_comparison' => $enitity_comparison,
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }

}
