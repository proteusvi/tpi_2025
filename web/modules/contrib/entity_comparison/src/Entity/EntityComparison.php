<?php

namespace Drupal\entity_comparison\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Defines the Entity comparison entity.
 *
 * @ConfigEntityType(
 *   id = "entity_comparison",
 *   label = @Translation("Entity comparison"),
 *   label_singular = @Translation("entity comparison"),
 *   label_plural = @Translation("entity comparisons"),
 *   handlers = {
 *     "list_builder" = "Drupal\entity_comparison\EntityComparisonListBuilder",
 *     "form" = {
 *       "add" = "Drupal\entity_comparison\Form\EntityComparisonForm",
 *       "edit" = "Drupal\entity_comparison\Form\EntityComparisonForm",
 *       "delete" = "Drupal\entity_comparison\Form\EntityComparisonDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\entity_comparison\EntityComparisonHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "entity_comparison",
 *   config_export = {
 *     "id",
 *     "label",
 *     "uuid",
 *     "add_link_text",
 *     "remove_link_text",
 *     "limit",
 *     "entity_type",
 *     "bundle_type"
 *   },
 *   admin_permission = "administer entity comparison",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "canonical" = "/compare/{entity_comparison}",
 *     "add-form" = "/admin/structure/entity_comparison/add",
 *     "edit-form" = "/admin/structure/entity_comparison/{entity_comparison}/edit",
 *     "delete-form" = "/admin/structure/entity_comparison/{entity_comparison}/delete",
 *     "collection" = "/admin/structure/entity_comparison"
 *   }
 * )
 */
class EntityComparison extends ConfigEntityBase implements EntityComparisonInterface {

  /**
   * The Entity comparison ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Entity comparison label.
   *
   * @var string
   */
  protected $label;

  /**
   * Add link's text.
   *
   * @var string
   */
  protected $add_link_text;

  /**
   * Remove link's text.
   *
   * @var string
   */
  protected $remove_link_text;

  /**
   * Limit.
   *
   * @var string
   */
  protected $limit;

  /**
   * The selected entity type.
   *
   * @var string
   */
  protected $entity_type;

  /**
   * The selected bundle type.
   *
   * @var string
   */
  protected $bundle_type;

  /**
   * {@inheritdoc}
   */
  public function getAddLinkText() {
    return $this->add_link_text;
  }

  /**
   * {@inheritdoc}
   */
  public function getRemoveLinkText() {
    return $this->remove_link_text;
  }

  /**
   * {@inheritdoc}
   */
  public function getLimit() {
    return $this->limit;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetEntityType() {
    return $this->entity_type;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetBundleType() {
    return $this->bundle_type;
  }

  /**
   * Load entity comparison entities by type and bundle.
   */
  public static function loadByEntityTypeAndBundleType($entity_type, $bundle_type) {
    $entity_comparison_list = [];

    $entity_comparisons = self::loadMultiple();

    foreach ($entity_comparisons as $entity_comparison) {
      if ($entity_type == $entity_comparison->getTargetEntityType() && $bundle_type == $entity_comparison->getTargetBundleType()) {
        $entity_comparison_list[] = $entity_comparison;
      }
    }

    return $entity_comparison_list;
  }

  /**
   * {@inheritdoc}
   */
  public function getLink($entity_id, $use_ajax = FALSE) {
    // Get session service.
    $session = \Drupal::service('session');

    // Get vurrent user's id.
    $uid = \Drupal::currentUser()->id();

    // Get entity type and bundle type.
    $entity_type = $this->getTargetEntityType();
    $bundle_type = $this->getTargetBundleType();

    // Get current entity comparison list.
    $entity_comparison_list = $session->get('entity_comparison_' . $uid);

    if (empty($entity_comparison_list)) {
      $add_link = TRUE;
    }
    else {
      if (!empty($entity_comparison_list[$entity_type][$bundle_type][$this->id()]) &&
          in_array($entity_id, $entity_comparison_list[$entity_type][$bundle_type][$this->id()])) {
        $add_link = FALSE;
      }
      else {
        $add_link = TRUE;
      }
    }

    // Get the url object from route.
    $url = Url::fromRoute('entity_comparison.action', [
      'entity_comparison_id' => $this->id(),
      'entity_id' => $entity_id,
    ], [
      'query' => \Drupal::service('redirect.destination')->getAsArray(),
      'attributes' => [
        'id' => 'entity-comparison-' . $this->id() . '-' . $entity_id,
        'data-entity-comparison' => $this->id() . '-' . $entity_id,
        'class' => [
          $use_ajax ? 'use-ajax' : '',
          $add_link ? 'add-link' : 'remove-link',
        ],
      ],
    ]);

    // Set link text.
    $link_text = $add_link ? $this->getAddLinkText() : $this->getRemoveLinkText();

    // Return with the link.
    return Link::fromTextAndUrl($link_text, $url);
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    if (!$update) {
      $this->createViewMode();

      // Flush all cache.
      drupal_flush_all_caches();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function toUrl($rel = 'canonical', array $options = []) {
    if ($rel == 'canonical') {
      $route_name = 'entity_comparison.compare.' . $this->id();
      $uri = new Url($route_name);

      // Pass the entity data through as options, so that alter functions do not
      // need to look up this entity again.
      $uri
        ->setOption('entity_type', $this->getEntityTypeId())
        ->setOption('entity', $this)
        ->setOption('language', $this->language());
      $uri_options = $uri
        ->getOptions();
      $uri_options += $options;
      return $uri
        ->setOptions($uri_options);
    }

    return parent::toUrl($rel, $options);
  }

  /**
   * Create and enable custom view mode.
   */
  protected function createViewMode() {
    // Generate an id for the view mode.
    $view_mode_id = $this->getViewModeId();
    $display_id = $this->getViewDisplayId();

    // Create new entity view mode if it doesn't exist.
    if (!EntityViewMode::load($view_mode_id)) {
      EntityViewMode::create([
        'id' => $view_mode_id,
        'label' => $this->label(),
        'targetEntityType' => $this->getTargetEntityType(),
      ])->save();
    }

    // Rebuild routes if needed.
    \Drupal::service('router.builder')->rebuildIfNeeded();

    $entity_display_repository = \Drupal::service('entity_display.repository');
    $display = $entity_display_repository->getViewDisplay($this->getTargetEntityType(), $this->getTargetBundleType(), $display_id);
    if (!$display) {
      // Load target bundle's default display.
      $default_display = $entity_display_repository->getViewDisplay($this->getTargetEntityType(), $this->getTargetBundleType());

      // Clone it for our new view mode.
      $display = $default_display->createCopy($display_id);

      // Save the display settings.
      $display->save();
    }

    // Change success message if Field UI is enabled.
    if (\Drupal::service('module_handler')->moduleExists('field_ui')) {
      // Get url to the view mode page.
      $url = $this->getOverviewUrl($display_id);

      // Show success message.
      \Drupal::messenger()->addMessage(t('The %display_mode mode now uses custom display settings. You might want to <a href=":url">configure them</a>.', [
        '%display_mode' => $this->label(),
        ':url' => $url->toString(),
      ]));
    }
    else {
      // Show success message.
      \Drupal::messenger()->addMessage(t('The %display_mode mode now uses custom display settings. To configure them, enable Field UI module.', [
        '%display_mode' => $this->label(),
      ]));
    }

    // Enable the created view mode on the target bundle's manage display page.
    $display->set('status', TRUE);
    $display->save();
  }

  /**
   * Get overview Url.
   */
  protected function getOverviewUrl($mode) {
    $entity_type = \Drupal::entityTypeManager()->getDefinition($this->getTargetEntityType());
    $bundle_parameter_key = $entity_type->getBundleEntityType() ?: 'bundle';
    return Url::fromRoute('entity.entity_view_display.' . $this->getTargetEntityType() . '.view_mode', [
      'view_mode_name' => $mode,
      $bundle_parameter_key => $this->getTargetBundleType(),
    ]);
  }

  /**
   * Get view mode ID.
   */
  protected function getViewModeId() : string {
    return $this->getTargetEntityType() . '.' . $this->getTargetBundleType() . '_' . $this->id();
  }

  /**
   * Get view display ID.
   */
  protected function getViewDisplayId() : string {
    return $this->getTargetBundleType() . '_' . $this->id();
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    parent::calculateDependencies();

    $this->addDependency('config', implode('.', [
      'core',
      'entity_view_mode',
      $this->getViewModeId(),
    ]));

    $this->addDependency('config', implode('.', [
      'core',
      'entity_view_display',
      $this->getTargetEntityType(),
      $this->getTargetBundleType(),
      $this->getViewDisplayId(),
    ]));
  }

}
