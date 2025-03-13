<?php

namespace Drupal\ui_patterns_field_formatters\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\Plugin\DataType\EntityReference;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\TypedData\Plugin\DataType\Uri;
use Drupal\Core\Url;
use Drupal\field_formatter\Plugin\Field\FieldFormatter\FieldWrapperBase;
use Drupal\text\TextProcessed;
use Drupal\ui_patterns\Form\PatternDisplayFormTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'pattern' formatter.
 *
 * Field types are altered in
 * ui_patterns_field_formatters_field_formatter_info_alter().
 *
 * @FieldFormatter(
 *   id = "pattern_all_formatter",
 *   label = @Translation("Pattern (one for all)"),
 *   field_types = {
 *     "string"
 *   },
 * )
 */
class PatternOneForAllFormatter extends FieldWrapperBase implements ContainerFactoryPluginInterface {

  use PatternDisplayFormTrait;

  /**
   * UI Patterns manager.
   *
   * @var \Drupal\ui_patterns\UiPatternsManager
   */
  protected $patternsManager;

  /**
   * UI Patterns source manager.
   *
   * @var \Drupal\ui_patterns\UiPatternsSourceManager
   */
  protected $sourceManager;

  /**
   * A module manager object.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->patternsManager = $container->get('plugin.manager.ui_patterns');
    $instance->sourceManager = $container->get('plugin.manager.ui_patterns_source');
    $instance->moduleHandler = $container->get('module_handler');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $settings = parent::defaultSettings();
    $settings['pattern'] = '';
    $settings['pattern_variant'] = '';
    $settings['pattern_mapping'] = [];
    // Used by ui_patterns_settings.
    $settings['pattern_settings'] = [];
    $settings['variants_token'] = [];
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    // Prevent nesting (unsupported yet).
    unset($form['type']['#options']['pattern_all_formatter']);
    unset($form['type']['#options']['pattern_each_formatter']);

    $form['warning'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['messages', 'messages--info']],
      '#weight' => -99,
      'message' => [
        '#markup' => $this->t('This formatter will render <strong>all</strong> field items in the same pattern.'),
      ],
    ];

    $field_storage_definition = $this->fieldDefinition->getFieldStorageDefinition();
    $context = [
      'storageDefinition' => $field_storage_definition,
      'limit' => $field_storage_definition->getPropertyNames(),
    ];
    // Some modifications to make 'variant' default value working.
    $configuration = $this->getSettings();

    $this->buildPatternDisplayForm($form, 'field_properties', $context, $configuration);

    $form['#element_validate'] = [[static::class, 'cleanSettings']];
    return $form;
  }

  /**
   * Clean up pattern settings.
   *
   * @param array $element
   *   The pattern settings element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $form
   *   The form definition.
   */
  public static function cleanSettings(array &$element, FormStateInterface $form_state, array $form) {
    $element_value = $form_state->getValue($element['#parents']);
    static::processFormStateValues($element_value);
    $form_state->setValue($element['#parents'], $element_value);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();

    $pattern = $this->getSetting('pattern');

    if (!empty($pattern)) {
      $pattern_definition = $this->patternsManager->getDefinition($pattern);

      $label = $this->t('None');
      if (!empty($this->getSetting('pattern'))) {
        $label = $pattern_definition->getLabel();
      }
      $summary[] = $this->t('Pattern: @pattern', ['@pattern' => $label]);

      $pattern_variant = $this->getSetting('pattern_variant');
      if (!empty($pattern_variant)) {
        $variant = $pattern_definition->getVariant($pattern_variant)->getLabel();
        $summary[] = $this->t('Variant: @variant', ['@variant' => $variant]);
      }
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    $mapping = $this->getSetting('pattern_mapping');
    $pattern = $this->getSetting('pattern');

    // Do not apply pattern when field value is empty.
    if ($items->isEmpty()) {
      return [];
    }

    // Set pattern fields.
    $fields = [];
    foreach ($mapping as $field) {
      if (isset($field['destination']) && $field['destination'] === '_hidden') {
        continue;
      }

      if ($field['plugin'] === 'field_meta_properties') {
        if ($field['source'] == '_label') {
          $fields[$field['destination']][] = $items->getFieldDefinition()->getLabel();
        }
        elseif ($field['source'] == '_field_display_label') {
          if ($this->moduleHandler->moduleExists('field_display_label')) {
            $fields[$field['destination']][] = $items->getFieldDefinition()->getThirdPartySetting('field_display_label', 'display_label');
          }
        }
        elseif ($field['source'] == '_entity_form_field_label') {
          if ($this->moduleHandler->moduleExists('entity_form_field_label')) {
            $fields[$field['destination']][] = $this->getThirdPartySetting('entity_form_field_label', 'new_label');
          }
        }
        elseif ($field['source'] == '_formatted') {
          $field_output = $this->getFieldOutput($items, $langcode);
          // Take the element children from the field output and return them.
          $children = Element::children($field_output);
          $fields[$field['destination']][] = array_intersect_key($field_output, array_flip($children));
        }
      }
      elseif ($field['plugin'] === 'field_raw_properties') {
        foreach ($items as $item) {
          $value = $this->extractValue($item, $field['source'], $langcode);
          if (NULL !== $value) {
            $fields[$field['destination']][] = $value;
          }
        }
      }
    }

    // Set pattern render array.
    $elements[0] = [
      '#type' => 'pattern',
      '#id' => $this->getSetting('pattern'),
      '#fields' => $fields,
      '#multiple_sources' => TRUE,
    ];

    // Set the variant.
    $pattern_variant = $this->getSetting('pattern_variant');
    if (!empty($pattern_variant)) {
      $elements[0]['#variant'] = $pattern_variant;
    }

    // Set the settings.
    $settings = $this->getSetting('pattern_settings');
    $pattern_settings = !empty($settings) && isset($settings[$pattern]) ? $settings[$pattern] : NULL;
    if (!empty($pattern_settings)) {
      $elements[0]['#settings'] = $pattern_settings;
    }

    // Set the variant tokens.
    $variant_tokens = $this->getSetting('variants_token');
    $variant_token = !empty($variant_tokens) && isset($variant_tokens[$pattern]) ? $variant_tokens[$pattern] : NULL;
    if (!empty($variant_tokens)) {
      $elements[0]['#variant_token'] = $variant_token;
    }

    // Set pattern context.
    $entity = $items->getEntity();
    if ($entity->getEntityType()->isTranslatable() && $entity->hasTranslation($langcode)) {
      $entity = $entity->getTranslation($langcode);
    }
    $elements[0]['#context'] = [
      'type' => 'field_formatter',
      'formatter' => [
        'id' => $this->getPluginId(),
        'class' => get_class($this),
      ],
      'entity' => $entity,
      'items' => $items,
      'item' => $items[0] ?? NULL,
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultValue(array $configuration, $field_name, $value) {
    // Some modifications to make 'destination' default value working.
    if (isset($configuration['pattern_mapping'][$field_name][$value])) {
      return $configuration['pattern_mapping'][$field_name][$value];
    }
    return NULL;
  }

  /**
   * Extract the given value from the item.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   The field item.
   * @param string $source
   *   The source property.
   * @param string $langcode
   *   The language that should be used to render the field.
   *
   * @return mixed|null
   *   The extracted value or NULL if the field is referencing an entity that
   *   doesn't exist anymore.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  protected function extractValue(FieldItemInterface $item, $source, string $langcode) {
    $property = $item->get($source);
    if ($property instanceof TextProcessed) {
      $value = $property->getValue();
    }
    elseif ($property instanceof EntityReference) {
      // Ensure the referenced entity still exists.
      if (empty($property->getTarget())) {
        return NULL;
      }

      $entity = $property->getTarget()->getEntity();
      // Drupal loads the entity in its default language and should load
      // the translated one if available.
      if ($entity->getEntityType()->isTranslatable() && $entity->hasTranslation($langcode)) {
        $translated_entity = $entity->getTranslation($langcode);
        $value = $translated_entity->label();
      }
      else {
        $value = $entity->label();
      }
    }
    else {
      $value = $property->getValue();
      $value = empty($value) ? '' : $value;
    }
    // Preprocess Uri datatype instead of pushing the raw value.
    if ($property instanceof Uri) {
      $options = [];
      // Most of the time, Uri datatype is met in a "link" field type.
      if ($item->getPluginId() === "field_item:link") {
        $options = $item->get('options')->toArray();
      }
      $value = Url::fromUri($value, $options)->toString();
    }
    return $value;
  }

}
