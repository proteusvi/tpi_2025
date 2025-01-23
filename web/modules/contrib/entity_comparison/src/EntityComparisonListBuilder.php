<?php

namespace Drupal\entity_comparison;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of Entity comparison entities.
 */
class EntityComparisonListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Entity comparison');
    $header['id'] = $this->t('Machine name');
    $header['url'] = $this->t('URL');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['label'] = $entity->label();
    $row['id'] = $entity->id();
    $row['url'] = $entity->toUrl();
    // You probably want a few more properties here...
    return $row + parent::buildRow($entity);
  }

}
