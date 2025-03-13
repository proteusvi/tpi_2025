<?php

namespace Drupal\ui_patterns_field_formatters\Plugin\UiPatterns\Source;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\ui_patterns\Plugin\PatternSourceBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines Field meta properties source plugin.
 *
 * @UiPatternsSource(
 *   id = "field_meta_properties",
 *   label = @Translation("Field meta properties"),
 *   tags = {
 *     "field_properties"
 *   }
 * )
 */
class FieldMetaPropertiesSource extends PatternSourceBase implements ContainerFactoryPluginInterface {

  /**
   * The module_handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->moduleHandler = $container->get('module_handler');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceFields() {
    $sources = [];
    $sources[] = $this->getSourceField('_label', 'Label');

    // Support Field Display Label module.
    if ($this->moduleHandler->moduleExists('field_display_label')) {
      $sources[] = $this->getSourceField('_field_display_label', 'Label (Field Display Label)');
    }

    // Support Entity Form/Display Field Label module.
    if ($this->moduleHandler->moduleExists('entity_form_field_label')) {
      $sources[] = $this->getSourceField('_entity_form_field_label', 'Label (Entity Form/Display Field Label)');
    }

    $sources[] = $this->getSourceField('_formatted', 'Formatted values');
    return $sources;
  }

}
