<?php

namespace Drupal\entity_comparison\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface for defining Entity comparison entities.
 */
interface EntityComparisonInterface extends ConfigEntityInterface {

  /**
   * Get add link's text.
   *
   * @return mixed
   *   Returns add link text.
   */
  public function getAddLinkText();

  /**
   * Get remove link's text.
   *
   * @return mixed
   *   Returns remove link text.
   */
  public function getRemoveLinkText();

  /**
   * Get limit.
   *
   * @return mixed
   *   Returns limits.
   */
  public function getLimit();

  /**
   * Get selected entity type.
   *
   * @return mixed
   *   Target entity types.
   */
  public function getTargetEntityType();

  /**
   * Get selected bundle type.
   *
   * @return mixed
   *   Target bundle types.
   */
  public function getTargetBundleType();

  /**
   * Get link array.
   *
   * @param int $entity_id
   *   Entity ID.
   * @param bool $use_ajax
   *   Use AJAX flag.
   *
   * @return mixed
   *   Returns links.
   */
  public function getLink($entity_id, $use_ajax = FALSE);

}
