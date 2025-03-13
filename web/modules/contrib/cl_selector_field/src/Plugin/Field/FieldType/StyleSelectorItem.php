<?php

namespace Drupal\cl_selector_field\Plugin\Field\FieldType;

use Drupal\Core\Extension\ExtensionLifecycle;
use Drupal\sdc\Component\ComponentMetadata;
use Drupal\sdc\Plugin\Component;
use Drupal\cl_editorial\Form\ComponentFiltersFormTrait;
use Drupal\cl_editorial\NoThemeComponentManager;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Defines the 'cl_selector_field_style_selector' field type.
 *
 * @FieldType(
 *   id = "cl_selector_field_style_selector",
 *   label = @Translation("Style Selector"),
 *   category = @Translation("General"),
 *   default_widget = "cl_selector_field_style_selector",
 *   default_formatter = "cl_selector_field_style_selector_table"
 * )
 */
class StyleSelectorItem extends FieldItemBase {

  use ComponentFiltersFormTrait;

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    $default_settings = [
      'features' => [],
      'filters' => [
        'forbidden' => [],
        'allowed' => [],
        'statuses' => [
          ExtensionLifecycle::STABLE,
          ExtensionLifecycle::EXPERIMENTAL,
          ExtensionLifecycle::DEPRECATED,
          ExtensionLifecycle::OBSOLETE,
        ],
      ],
    ];
    return $default_settings + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $settings = $this->getSettings();
    $message = $this->t('Set a combination of filters to control the list of components that will become blocks.');
    $parents = ['settings'];
    $form_settings = $settings['filters'];
    $component_manager = \Drupal::service(NoThemeComponentManager::class);
    static::buildSettingsForm($form, $form_state, $component_manager, $form_settings, $parents, $message);

    $form['features'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Supported features'),
      '#description' => $this->t('Some additional contributed modules let you specify additional information on your components. Choose which of those features this field should collect.'),
      '#default_value' => $settings['features'],
      '#options' => [],
      '#empty' => $this->t('There are no features currently available.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public static function fieldSettingsToConfigData(array $settings) {
    // Allowed anf forbidden are nested in the form due to AJAX reasons. We undo
    // that here for config storage.
    $clean = static fn (array $item) => array_values(array_filter($item));
    $new_settings = [
      'features' => $settings['features'],
      'filters' => [
        'statuses' => $clean($settings['filters']['statuses'] ?? []),
        'allowed' => $clean($settings['filters']['refine']['allowed'] ?? []),
        'forbidden' => $clean($settings['filters']['statuses']['forbidden'] ?? []),
      ],
    ];
    return parent::fieldSettingsToConfigData($new_settings);
  }

  /**
   * Returns all the allowed values for 'component' sub-field grouped by letter.
   *
   * @param array $allowed
   *   The allowed components.
   * @param array $forbidden
   *   The forbidden components.
   *
   * @return array
   *   The list of allowed values.
   */
  private function allAllowedComponents(array $allowed, array $forbidden): array {
    $component_manager = \Drupal::service(NoThemeComponentManager::class);
    assert($component_manager instanceof NoThemeComponentManager);
    $components = $component_manager->getFilteredComponentTypes(
        $allowed,
        $forbidden,
        [
          ExtensionLifecycle::STABLE,
          ExtensionLifecycle::EXPERIMENTAL,
          ExtensionLifecycle::DEPRECATED,
          ExtensionLifecycle::OBSOLETE,
        ]
      );
    return array_reduce(
      $components,
      static fn(array $carry, Component $component) => [
        ...$carry,
        $component->getPluginId() => $component,
      ],
      []
    );
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    return $this->component === NULL;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['component'] = DataDefinition::create('string')
      ->setLabel(t('Component'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints() {
    $constraints = parent::getConstraints();
    $clean = static fn(array $a) => array_values(array_filter($a));
    $definition = $this->getFieldDefinition();
    $filters = $definition->getSetting('filters');
    $allowed = $clean($filters['allowed'] ?? []);
    $forbidden = $clean($filters['forbidden'] ?? []);
    $values = $this->allAllowedComponents($allowed, $forbidden);
    $options['component'] = [];
    if (!empty($this->values)) {
      $options['component']['AllowedValues'] = array_keys($values);
      $options['component']['AllowedValues'][] = NULL;
    }

    $constraint_manager = $this->getTypedDataManager()
      ->getValidationConstraintManager();
    $constraints[] = $constraint_manager->create('ComplexData', $options);
    return $constraints;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'component' => [
          'type' => 'varchar',
          'length' => 255,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    return [
      'component' => 'my-component',
    ];
  }

}
