<?php

namespace Drupal\ui_patterns_field_formatters\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'pattern' formatter.
 *
 * Field types are altered in
 * ui_patterns_field_formatters_field_formatter_info_alter().
 *
 * @FieldFormatter(
 *   id = "pattern_each_formatter",
 *   label = @Translation("Pattern (one for each)"),
 *   field_types = {
 *     "string"
 *   },
 * )
 */
class PatternOneForEachFormatter extends PatternOneForAllFormatter {

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);
    $form['warning']['message']['#markup'] = $this->t('This formatter will render <strong>each</strong> field item in its own pattern.');
    return $form;
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

    // Prepare field output to be used later if needed.
    if (isset($mapping['field_meta_properties:_formatted']['destination']) &&
      $mapping['field_meta_properties:_formatted']['destination'] !== '_hidden') {
      $field_output = $this->getFieldOutput($items, $langcode);
    }

    foreach ($items as $delta => $item) {
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
          elseif ($field['source'] == '_formatted') {
            $fields[$field['destination']][] = $field_output[$delta];
          }
        }
        elseif ($field['plugin'] === 'field_raw_properties') {
          $value = $this->extractValue($item, $field['source'], $langcode);
          if (NULL !== $value) {
            $fields[$field['destination']][] = $value;
          }
        }
      }

      // Set pattern render array.
      $elements[$delta] = [
        '#type' => 'pattern',
        '#id' => $this->getSetting('pattern'),
        '#fields' => $fields,
        '#multiple_sources' => TRUE,
      ];

      // Set the variant.
      $pattern_variant = $this->getSetting('pattern_variant');
      if (!empty($pattern_variant)) {
        $elements[$delta]['#variant'] = $pattern_variant;
      }

      // Set the settings.
      $settings = $this->getSetting('pattern_settings');
      $pattern_settings = !empty($settings) && isset($settings[$pattern]) ? $settings[$pattern] : NULL;
      if (!empty($pattern_settings)) {
        $elements[$delta]['#settings'] = $pattern_settings;
      }

      // Set the variant tokens.
      $variant_tokens = $this->getSetting('variants_token');
      $variant_token = !empty($variant_tokens) && isset($variant_tokens[$pattern]) ? $variant_tokens[$pattern] : NULL;
      if (!empty($variant_tokens)) {
        $elements[$delta]['#variant_token'] = $variant_token;
      }

      // Set pattern context.
      $entity = $items->getEntity();
      if ($entity->getEntityType()->isTranslatable() && $entity->hasTranslation($langcode)) {
        $entity = $entity->getTranslation($langcode);
      }
      $elements[$delta]['#context'] = [
        'type' => 'field_formatter',
        'formatter' => [
          'id' => $this->getPluginId(),
          'class' => get_class($this),
        ],
        'entity' => $entity,
        'items' => $items,
        'item' => $item,
      ];
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    $cardinality = $field_definition->getFieldStorageDefinition()->getCardinality();
    return $cardinality === FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED || $cardinality > 1;
  }

}
