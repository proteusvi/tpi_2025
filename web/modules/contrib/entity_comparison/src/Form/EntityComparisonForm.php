<?php

namespace Drupal\entity_comparison\Form;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\ProxyClass\Routing\RouteBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class EntityComparisonForm.
 *
 * @package Drupal\entity_comparison\Form
 */
class EntityComparisonForm extends EntityForm {

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * Entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Route builder service.
   *
   * @var \Drupal\Core\Routing\RouteBuilder
   */
  protected $routerBuilder;

  /**
   * Class constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   Entity type bundle info service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   Entity field manager service.
   * @param \Drupal\Core\Routing\RouteBuilder $router_builder
   *   Route builder service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info, EntityFieldManagerInterface $entity_field_manager, RouteBuilder $router_builder) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->entityFieldManager = $entity_field_manager;
    $this->routerBuilder = $router_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    return new static(
    // Load the service required to construct this class.
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_field.manager'),
      $container->get('router.builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $entity_comparison = $this->entity;

    // Label.
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $entity_comparison->label(),
      '#description' => $this->t('Label for the Entity comparison (For example: Product)'),
      '#required' => TRUE,
    ];

    // ID.
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $entity_comparison->id(),
      '#machine_name' => [
        'exists' => '\Drupal\entity_comparison\Entity\EntityComparison::load',
      ],
      '#disabled' => !$entity_comparison->isNew(),
    ];

    // Add link text.
    $form['add_link_text'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Text for the link "Add to comparison list"'),
      '#default_value' => !empty($entity_comparison->getAddLinkText()) ? $entity_comparison->getAddLinkText() : $this->t('Add to comparison list'),
    ];

    // Remove link text.
    $form['remove_link_text'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Text for the link to "Remove from the comparison"'),
      '#default_value' => !empty($entity_comparison->getRemoveLinkText()) ? $entity_comparison->getRemoveLinkText() : $this->t('Remove from the comparison'),
    ];

    // Limit.
    $form['limit'] = [
      '#type' => 'number',
      '#title' => $this->t('The limit on the number of compared items ("0" - no limit)'),
      '#min' => 0,
      '#required' => TRUE,
      '#step' => 1,
      '#default_value' => !empty($entity_comparison->getLimit()) ? $entity_comparison->getLimit() : 0,
    ];

    // Entity.
    $form['entity_type'] = [
      '#type' => 'select',
      '#required' => TRUE,
      '#title' => $this->t('Entity'),
      '#default_value' => $entity_comparison->getTargetEntityType(),
      '#options' => $this->getEntityList(),
      '#ajax' => [
        'callback' => '::entitySelected',
        'wrapper' => 'entity-comparison-container',
        'event' => 'change',
        'progress' => [
          'type' => 'throbber',
        ],
      ],
      '#disabled' => !$entity_comparison->isNew(),
    ];

    $form['container'] = [
      '#type' => 'container',
      '#prefix' => '<div id="entity-comparison-container">',
      '#suffix' => '</div>',
    ];

    $entity_type = !empty($form_state->getValue('entity_type')) ? $form_state->getValue('entity_type') : $form['entity_type']['#default_value'];

    if (!empty($entity_type)) {
      // Bundle.
      $form['container']['bundle_type'] = [
        '#type' => 'select',
        '#required' => TRUE,
        '#title' => $this->t('Bundle'),
        '#default_value' => $entity_comparison->getTargetBundleType(),
        '#options' => $this->getBundleList($entity_type),
        '#disabled' => !$entity_comparison->isNew(),
      ];

    }

    /* You will need additional form elements for your custom properties. */

    $form['help_text'] = [
      '#type' => 'markup',
      '#markup' => '<p>' . $this->t("After saving this entity comparison, a new view mode will be created on the related entity type's bundle type's Manage display page and you can see a link to that Manage display page, on the entity comparison list page. On the manage display page, you can select which fields you would like to see in the comparison list, you can rearrange fields and select field formatters for each fields.") . '</p>',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity_comparison = $this->entity;
    $status = $entity_comparison->save();

    switch ($status) {
      case SAVED_NEW:
        \Drupal::messenger()->addMessage($this->t('Created the %label Entity comparison.', [
          '%label' => $entity_comparison->label(),
        ]));
        break;

      default:
        \Drupal::messenger()->addMessage($this->t('Saved the %label Entity comparison.', [
          '%label' => $entity_comparison->label(),
        ]));
    }
    $form_state->setRedirectUrl($entity_comparison->toUrl('collection'));
  }

  /**
   * Get entity list.
   *
   * @return array
   *   Array of entity names.
   */
  protected function getEntityList() {
    $list = [];

    $entity_list = $this->entityTypeManager->getDefinitions();

    foreach ($entity_list as $entity_type => $entity_type_definition) {
      if ($entity_type_definition->entityClassImplements(ContentEntityInterface::class)) {
        $list[$entity_type] = $entity_type_definition->getLabel();
      }
    }

    asort($list);

    return $list;
  }

  /**
   * Get bundles of an entity.
   *
   * @param string $entity_type
   *   Entity type.
   *
   * @return array
   *   Array of bundle names.
   */
  protected function getBundleList($entity_type) {
    $list = [];
    $bundle_list = $this->entityTypeBundleInfo->getBundleInfo($entity_type);

    foreach ($bundle_list as $bundle_type => $bundle_name) {
      $list[$bundle_type] = $bundle_name['label'];
    }

    return $list;
  }

  /**
   * Returns form container.
   */
  public function entitySelected(array &$form, FormStateInterface $form_state) {
    return $form['container'];
  }

  /**
   * Get fields of a bundle.
   *
   * @param string $entity_type
   *   Entity type.
   * @param string $bundle_type
   *   Bundle type.
   *
   * @return array
   *   Array of fields.
   */
  protected function getFields($entity_type, $bundle_type) {
    $list = [];

    foreach ($this->entityFieldManager->getFieldDefinitions($entity_type, $bundle_type) as $field_key => $field) {
      $list[$field_key] = $field->getLabel();
    }

    return $list;
  }

}
