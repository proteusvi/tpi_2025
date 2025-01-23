<?php

namespace Drupal\entity_comparison\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides block plugin definitions for entity comparisons.
 *
 * @see \Drupal\entity_comparison\Plugin\Block\EntityComparisonBlock
 */
class EntityComparisonBlock extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The entity comparison storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $entityComparison;

  /**
   * Constructs new EntityComparisonBlock.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $entity_comparison
   *   The entity comparison storage.
   */
  public function __construct(EntityStorageInterface $entity_comparison) {
    $this->entityComparison = $entity_comparison;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity_type.manager')->getStorage('entity_comparison')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    foreach ($this->entityComparison->loadMultiple() as $id => $entity) {
      $this->derivatives[$id] = $base_plugin_definition;
      $this->derivatives[$id]['admin_label'] = $entity->label();
      $this->derivatives[$id]['config_dependencies']['config'] = [$entity->getConfigDependencyName()];
    }
    return $this->derivatives;
  }

}
