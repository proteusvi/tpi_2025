<?php

namespace Drupal\cl_selector_field\Plugin\Field\FieldFormatter;

use Drupal\sdc\ComponentPluginManager;
use Drupal\sdc\Exception\ComponentNotFoundException;
use Drupal\cl_selector_field\Plugin\Field\FieldType\StyleSelectorItem;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'cl_selector_field_style_selector_table' formatter.
 *
 * @FieldFormatter(
 *   id = "cl_selector_field_style_selector_table",
 *   label = @Translation("Table"),
 *   field_types = {"cl_selector_field_style_selector"}
 * )
 */
class StyleSelectorTableFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, protected readonly ComponentPluginManager $componentPluginManager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $component_plugin_manager = $container->get('plugin.manager.sdc');
    assert($component_plugin_manager instanceof ComponentPluginManager);
    return new static($plugin_id, $plugin_definition, $configuration['field_definition'], $configuration['settings'], $configuration['label'], $configuration['view_mode'], $configuration['third_party_settings'], $component_plugin_manager);
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $cardinality = $items->getFieldDefinition()
      ->getFieldStorageDefinition()
      ->getCardinality();
    if ($cardinality > 1) {
      $header[] = '#';
    }
    $header[] = $this->t('Component');

    $table = [
      '#type' => 'table',
      '#header' => $header,
    ];

    foreach ($items as $delta => $item) {
      if (!$item instanceof StyleSelectorItem) {
        continue;
      }
      $row = [];
      if ($cardinality > 1) {
        $row[]['#markup'] = $delta + 1;
      }

      try {
        $component = $this->componentPluginManager->find($item->component);
        $row[]['#markup'] = $component->metadata->name;
      }
      catch (ComponentNotFoundException $e) {
        $row[]['#markup'] = '';
      }

      $table[$delta] = $row;
    }

    return [$table];
  }

}
