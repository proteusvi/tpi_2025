<?php

namespace Drupal\cl_block\Form;

use Drupal\cl_components\Exception\ComponentNotFoundException;
use Drupal\cl_components\Exception\InvalidComponentHookException;
use Drupal\cl_editorial\NoThemeComponentManager;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\editor\Ajax\EditorDialogSave;
use Drupal\editor\EditorInterface;
use Drupal\embed\EmbedButtonInterface;
use Shaper\Util\Context;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to embed URLs.
 */
class ClBlockDialog extends FormBase {

  /**
   * Constructs a ElementEmbedDialog object.
   *
   * @param \Drupal\cl_editorial\NoThemeComponentManager $componentManager
   *   The component manager.
   */
  public function __construct(protected NoThemeComponentManager $componentManager) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get(NoThemeComponentManager::class)
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cl_block_dialog';
  }

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\editor\EditorInterface $editor
   *   The editor to which this dialog corresponds.
   * @param \Drupal\embed\EmbedButtonInterface $embed_button
   *   The URL button to which this dialog corresponds.
   */
  public function buildForm(array $form, FormStateInterface $form_state, EditorInterface $editor = NULL, EmbedButtonInterface $embed_button = NULL) {
    $values = $form_state->getValues();
    $input = $form_state->getUserInput();
    // Set URL button element in form state, so that it can be used later in
    // validateForm() function.
    $form_state->set('embed_button', $embed_button);
    $form_state->set('editor', $editor);
    // Initialize URL element with form attributes, if present.
    $cl_component = $values['attributes'] ?? [];
    $cl_component += $input['attributes'] ?? [];
    // If we use the user input directly, the component ID value is in the
    // 'machine_name'.
    if ($cl_component['data-component-id']['machine_name'] ?? FALSE) {
      $cl_component['data-component-id'] = $cl_component['data-component-id']['machine_name'];
    }
    // The default values are set directly from \Drupal::request()->request,
    // provided by the editor plugin opening the dialog.
    $initial_default_value = $input['editor_object']['data-component-id'] ?? NULL;
    if (!$form_state->get('cl_component')) {
      $form_state->set('cl_component', $input['editor_object'] ?? []);
    }
    $cl_component += $form_state->get('cl_component');
    $cl_component += [
      'data-component-id' => $embed_button->getTypeSetting('component_id'),
      'data-component-settings' => [],
    ];

    if (is_string($cl_component['data-component-settings'])) {
      $cl_component['data-component-settings'] = Json::decode($cl_component['data-component-settings']);
    }

    $form_state->set('cl_component', $cl_component);

    $form['#tree'] = TRUE;
    $form['#attached']['library'][] = 'editor/drupal.editor.dialog';
    $form['#attached']['library'][] = 'cl_block/dialog';
    $form['#prefix'] = '<div id="cl-block-dialog-form">';
    $form['#suffix'] = '</div>';
    // @todo Figure out if this is messing with the required fields validation.
    $form['#limit_validation_errors'] = [];
    $form['#attributes']['class'] = ['cl-block-dialog-form'];

    $form['attributes'] = [
      '#type' => 'container',
      'data-component-id' => [
        '#type' => 'cl_component_selector',
        '#title' => $this->t('CL Component'),
        '#required' => TRUE,
        '#default_value' => $cl_component['data-component-id'] ?? NULL,
        '#attributes' => ['style' => sprintf('display: %s;', $initial_default_value ? 'none' : 'block')],
        '#ajax' => ['wrapper' => 'cl-block-dialog-form'],
      ],
    ];
    try {
      $component = $this->componentManager->findWithoutThemeFilter($cl_component['data-component-id'] ?? '');
    }
    catch (ComponentNotFoundException $e) {
      return $form;
    }

    $component_form = [
      '#tree' => TRUE,
      '#process' => [
        [$this, 'componentDataProcessCallback'],
      ],
    ];
    $component_form['variant'] = [
      '#type' => 'radios',
      '#title' => $this->t('Variant'),
      '#options' => ClComponentForm::getComponentVariantOptions($component),
      '#default_value' => $cl_component['data-component-settings']['variant'] ?? '',
      '#ajax' => [
        'callback' => [ClComponentForm::class, 'twigBlockAjaxCallback'],
        'wrapper' => 'twig-blocks-wrapper',
        'effect' => 'fade',
      ],
    ];

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
    $component_form['data'] = $component_form['data'] ?? [];
    $element = &$component_form['data'];
    $current_input = $cl_component['data-component-settings']['data'] ?? [];
    $context = new Context([
      'form_state' => $form_state,
      'current_input' => $current_input,
      'form' => $form,
    ]);
    $element = \Drupal::service('cl_block.form_generator')
      ->transform($schema, $context);
    $element['#weight'] = 2;
    // Add the component data to the form via AJAX.
    $component_form['twig_blocks_wrapper'] = [
      '#prefix' => '<div id="twig-blocks-wrapper">',
      '#suffix' => '</div>',
      '#weight' => 5,
    ];
    try {
      $component_form = $component->invokeHook('form_alter', [
        $component_form,
        $form_state,
      ]);
    }
    catch (InvalidComponentHookException $e) {
      // The component does not support this hook. It is fine, do nothing.
    }
    if (\Drupal::moduleHandler()->moduleExists('token')) {
      $form['tree'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => ['node', 'user'],
      ];
    }
    $form['attributes']['data-component-settings'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#required' => TRUE,
    ];
    $form['attributes']['data-component-settings'] += $component_form;

    $form['attributes']['data-embed-button'] = [
      '#type' => 'value',
      '#value' => $embed_button->id(),
    ];
    $form['attributes']['component-name'] = [
      '#type' => 'value',
      '#value' => sprintf(
        'CL Component: %s',
        $component->getMetadata()->getName()
      ),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['save_modal'] = [
      '#type' => 'submit',
      '#value' => $this->t('Embed component'),
      '#button_type' => 'primary',
      // No regular submit-handler. This form only works via JavaScript.
      '#submit' => [],
      '#ajax' => [
        'callback' => '::submitForm',
        'event' => 'click',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    $values = $form_state->getValues();
    // Display errors in form, if any.
    if ($form_state->isRebuilding()) {
      return $form;
    }
    if ($form_state->hasAnyErrors()) {
      unset($form['#prefix'], $form['#suffix']);
      $form['status_messages'] = [
        '#type' => 'status_messages',
        '#weight' => -10,
      ];
      $response->addCommand(new HtmlCommand('#cl-block-dialog-form', $form));
      return $response;
    }
    $values['attributes']['data-component-id'] = $values['attributes']['data-component-id']['machine_name'] ?? '';
    // Serialize entity embed settings to JSON string.
    if (!empty($values['attributes']['data-component-settings'])) {
      $values['attributes']['data-component-settings'] = Json::encode($values['attributes']['data-component-settings']);
    }

    // Allow other modules to alter the values before getting submitted to the
    // WYSIWYG.
    \Drupal::moduleHandler()->alter('cl_block_values', $values);

    $response->addCommand(new EditorDialogSave($values));
    $response->addCommand(new CloseModalDialogCommand());
    return $response;
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
      $machine_name = $form_state->getValue([
        'attributes',
        'data-component-id',
        'machine_name',
      ]);
      $component = $this->componentManager->findWithoutThemeFilter($machine_name);
    }
    catch (ComponentNotFoundException $e) {
      watchdog_exception('cl_block', $e);
      return $element;
    }
    $variant = ClComponentForm::getVariantFromForm([
      ...$element['#parents'],
      'variant',
    ], $form_state) ?? $cl_component['data-component-variant'] ?? '';
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
      $current_format = $current_data[$block_name]['format'] ?? 'full_html_with_twig';
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
    $twig_blocks['#parents'] = [...$element['#parents'], 'twig_blocks'];
    $element['#weight'] = 5;
    return $element;
  }

}
