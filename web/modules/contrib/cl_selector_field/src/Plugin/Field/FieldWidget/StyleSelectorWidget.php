<?php

namespace Drupal\cl_selector_field\Plugin\Field\FieldWidget;

use Drupal\sdc\Exception\ComponentNotFoundException;
use Drupal\sdc\Plugin\Component;
use Drupal\cl_editorial\NoThemeComponentManager;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the 'cl_selector_field_style_selector' field widget.
 *
 * @FieldWidget(
 *   id = "cl_selector_field_style_selector",
 *   label = @Translation("Style Selector"),
 *   field_types = {"cl_selector_field_style_selector"},
 * )
 */
class StyleSelectorWidget extends WidgetBase {

  /**
   * The component manager.
   *
   * @var \Drupal\cl_editorial\NoThemeComponentManager
   */
  private NoThemeComponentManager $componentManager;

  /**
   * The path where Drupal is installed.
   *
   * @var string
   */
  private string $appRoot;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, NoThemeComponentManager $component_manager, string $app_root) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->componentManager = $component_manager;
    $this->appRoot = $app_root;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $component_manager = $container->get(NoThemeComponentManager::class);
    assert($component_manager instanceof NoThemeComponentManager);
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $component_manager,
      $container->getParameter('app.root')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $item = $items[$delta] ?? NULL;
    $settings = $items->getSettings();
    $all_parents = array_merge(
      $element['#field_parents'],
      [$items->getName(), $delta, 'component']
    );
    $all_user_input = $form_state->getUserInput();
    $user_input = NestedArray::getValue($all_user_input, $all_parents) ?? [];
    $selected_component = NestedArray::getValue($user_input, ['machine_name']);
    $selected_component = !is_null($selected_component)
      ? $selected_component
      : ($item->component ?? NULL);
    if (!$selected_component) {
      // Set the default value for the field, if any.
      $field_definition = $item->getFieldDefinition();
      $selected_component = $field_definition->get('default_value')[$delta]['component'] ?? NULL;
    }
    $filter_props = ['allowed', 'forbidden', 'statuses'];
    $filters = array_intersect_key($settings['filters'], array_flip($filter_props));
    $component_element = [
      ...$element,
      '#type' => 'cl_component_selector',
      '#default_value' => ['machine_name' => $selected_component],
      '#filters' => $filters,
    ];
    $field_name = $items->getFieldDefinition()->getName();
    $wrapper_id = Html::getId(sprintf('%s-%d-form-element-wrapper', $field_name, $delta));
    $element = ['component' => $component_element];
    return [
      '#type' => 'container',
      '#attributes' => ['id' => $wrapper_id],
      ...$element,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as $delta => $value) {
      $machine_name = $value['component']['machine_name'] ?? '';
      if ($machine_name === '') {
        $values[$delta]['component'] = NULL;
        continue;
      }
      $values[$delta]['component'] = $machine_name;
    }
    return $values;
  }

}
