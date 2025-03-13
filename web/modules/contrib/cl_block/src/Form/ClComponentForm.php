<?php

namespace Drupal\cl_block\Form;

use Drupal\cl_components\Exception\ComponentNotFoundException;
use Drupal\cl_components\Exception\InvalidComponentHookException;
use Drupal\cl_components\Plugin\Component;
use Drupal\cl_editorial\NoThemeComponentManager;
use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Shaper\Transformation\TransformationInterface;
use Shaper\Util\Context;

/**
 * Helper class to build forms for CL Components.
 */
class ClComponentForm {

  use StringTranslationTrait;

  /**
   * The form configuration.
   *
   * @var array
   */
  protected array $config;

  /**
   * CL Component form constructor.
   *
   * @param string $componentName
   *   The component name.
   * @param \Shaper\Transformation\TransformationInterface $formGenerator
   *   The form generator.
   * @param \Drupal\cl_editorial\NoThemeComponentManager $componentManager
   *   The component manager.
   */
  public function __construct(
    protected readonly string $componentName,
    protected readonly TransformationInterface $formGenerator,
    protected readonly NoThemeComponentManager $componentManager
  ) {
  }

  /**
   * Render API callback: gets the layout settings elements.
   */
  public static function twigBlockAjaxCallback(array $form, FormStateInterface $form_state) {
    $twig_block_array_parents = $form_state->get([
      $form['#id'],
      'twig_block_array_parents',
    ]) ?? [];
    return NestedArray::getValue($form, array_merge($twig_block_array_parents, ['twig_blocks_wrapper']));
  }

  /**
   * Builds the form for a component.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $config
   *   The config.
   *
   * @return array
   *   The form.
   *
   * @throws \Drupal\cl_components\Exception\ComponentNotFoundException
   */
  public function buildForm(array $form, FormStateInterface $form_state, array $config): array {
    $this->config = $config;
    $form['component'] = [
      '#tree' => TRUE,
      '#process' => [
        [$this, 'componentDataProcessCallback'],
      ],
    ];

    $twig_block_wrapper_id = sprintf('twig-blocks-wrapper--%s', Crypt::randomBytesBase64(8));
    $form['component']['variant'] = [
      '#type' => 'radios',
      '#title' => $this->t('Variant'),
      '#options' => static::getComponentVariantOptions($this->getComponent()),
      '#default_value' => $this->config['component']['variant'] ?? '',
      '#ajax' => [
        'callback' => [static::class, 'twigBlockAjaxCallback'],
        'wrapper' => $twig_block_wrapper_id,
        'effect' => 'fade',
      ],
    ];

    try {
      $component = $this->getComponent();
    }
    catch (ComponentNotFoundException $e) {
      return $form;
    }
    // Here is where we derive the form from the metadata.
    $metadata = $component->getMetadata();
    $schemas = $metadata->getSchemas();
    $component_schema = $schemas['props'] ?? [];
    // Encode & decode, so we transform an associative array to an stdClass
    // recursively.
    try {
      $schema = json_decode(
        json_encode(
          $component_schema,
          JSON_THROW_ON_ERROR
        ),
        FALSE,
        512,
        JSON_THROW_ON_ERROR
      );
    }
    catch (\JsonException $e) {
      $schema = (object) [];
    }
    $form['component']['data'] = $form['component']['data'] ?? [];
    $element = &$form['component']['data'];
    $current_input = $this->config['component']['data'] ?? [];
    $context = new Context([
      'form_state' => $form_state,
      'current_input' => $current_input,
      'form' => $form,
    ]);
    $element = $this->formGenerator->transform($schema, $context);
    $element['#weight'] = 2;
    // Add the component data to the form via AJAX.
    $form['component']['twig_blocks_wrapper'] = [
      '#prefix' => '<div id="' . $twig_block_wrapper_id . '">',
      '#suffix' => '</div>',
      '#weight' => 5,
    ];
    if (\Drupal::moduleHandler()->moduleExists('token')) {
      $form['tree'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => ['node', 'user'],
      ];
    }
    try {
      $form['component'] = $component->invokeHook('form_alter', [
        $form['component'],
        $form_state,
      ]);
    }
    catch (InvalidComponentHookException $e) {
      // The component does not support this hook. It is fine, do nothing.
    }
    return $form;
  }

  /**
   * Get the available options for the variant selector.
   *
   * @return array
   *   The variant options.
   */
  public static function getComponentVariantOptions(Component $component): array {
    $variants = $component->getVariants();
    $toHuman = static fn(string $variant) => ucfirst(strtr($variant, '-', ' '));
    return [
      '' => new TranslatableMarkup('-Default variant-'),
      ...array_combine($variants, array_map($toHuman, $variants)),
    ];
  }

  /**
   * Gets the component.
   *
   * @return \Drupal\cl_components\Plugin\Component
   *   The component.
   *
   * @throws \Drupal\cl_components\Exception\ComponentNotFoundException
   */
  protected function getComponent(): Component {
    if (empty($this->component)) {
      $this->component = $this->componentManager->findWithoutThemeFilter($this->componentName);
    }
    return $this->component;
  }

  /**
   * Render API callback: builds the component data elements.
   */
  public function componentDataProcessCallback(array &$element, FormStateInterface $form_state, array $form): array {
    // Store the array parents for our element so that we can retrieve the
    // twig block settings in our AJAX callback.
    $form_state->set([
      $form['#id'],
      'twig_block_array_parents',
    ], $element['#array_parents']);
    try {
      $component = $this->getComponent();
    }
    catch (ComponentNotFoundException $e) {
      watchdog_exception('cl_block', $e);
      return $element;
    }
    $variant = static::getVariantFromForm([
      ...$element['#parents'],
      'variant',
    ], $form_state) ?? $this->config['component']['variant'] ?? '';
    // Get the Twig block names, so we can make them configurable.
    $metadata = $component->getMetadata();
    $component_schema = $metadata->getSchemas()['named_blocks'][$variant] ?? [];
    if (empty($component_schema)) {
      return $element;
    }
    $twig_blocks = &$element['twig_blocks_wrapper']['twig_blocks'];
    $current_data = $this->config['component']['twig_blocks'] ?? [];
    // Process all block names.
    $block_names = array_keys($component_schema['properties'] ?? []);
    foreach ($block_names as $block_name) {
      $current_format = $current_data[$block_name]['format'] ?? NULL;
      $current_value = $current_data[$block_name]['value'] ?? NULL;
      $twig_blocks[$block_name] = [
        '#type' => 'text_format',
        '#title' => $this->t(
          'Twig Block: @name',
          ['@name' => $block_name]
        ),
        '#format' => $current_format,
        '#default_value' => $current_value,
      ];
    }
    $twig_blocks['#parents'] = array_merge(
      $element['#parents'],
      ['twig_blocks']
    );
    $element['#weight'] = 5;
    return $element;
  }

  /**
   * Gets the formatter object.
   *
   * @param array $parents
   *   The #parents of the element representing the formatter.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return string|null
   *   The variant.
   */
  public static function getVariantFromForm(array $parents, FormStateInterface $form_state): ?string {
    // Use the processed values, if available.
    $variant = NestedArray::getValue($form_state->getValues(), $parents);
    if (isset($variant)) {
      return $variant;
    }
    // Next check the raw user input.
    return NestedArray::getValue($form_state->getUserInput(), $parents);
  }

}
