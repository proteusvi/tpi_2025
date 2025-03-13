<?php

namespace Drupal\cl_block\Plugin\Block;

use Drupal\cl_block\Form\ClComponentForm;
use Drupal\cl_block\Render\ComponentBlockRenderer;
use Drupal\cl_components\Plugin\Component;
use Drupal\cl_editorial\NoThemeComponentManager;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block that renders a component.
 *
 * @Block(
 *   id = "cl_component_block",
 *   deriver = "\Drupal\cl_block\Plugin\Derivative\ComponentBlockDeriver",
 *   context_definitions = {
 *     "language" = @ContextDefinition("language", required = FALSE, label = @Translation("Language")),
 *     "node" = @ContextDefinition("entity:node", required = FALSE,
 *       label = @Translation("Node")),
 *     "user" = @ContextDefinition("entity:user", required = FALSE,
 *       label = @Translation("User Context"), constraints = { "NotNull" = {} },
 *     ),
 *   }
 * )
 *
 * @internal
 *   Plugin classes are internal.
 */
class ComponentBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The field name.
   *
   * @var string
   */
  protected string $componentName;

  /**
   * The component.
   *
   * @var \Drupal\cl_components\Plugin\Component
   */
  protected Component $component;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * The block renderer.
   *
   * @var \Drupal\cl_block\Render\ComponentBlockRenderer
   */
  protected ComponentBlockRenderer $renderer;

  /**
   * Does the site support inject.
   *
   * @var bool
   */
  protected bool $supportsInject = FALSE;

  /**
   * The form helper.
   *
   * @var \Drupal\cl_block\Form\ClComponentForm
   */
  private ClComponentForm $formHelper;

  /**
   * Constructs a new FieldBlock.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\cl_block\Form\ClComponentForm $form_helper
   *   The form helper.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\cl_block\Render\ComponentBlockRenderer $renderer
   *   The block renderer.
   * @param bool $supports_inject
   *   TRUE if the site supports the inject syntax.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ClComponentForm $form_helper,
    LoggerInterface $logger,
    ComponentBlockRenderer $renderer,
    bool $supports_inject
  ) {
    $this->logger = $logger;
    $this->renderer = $renderer;
    $this->formHelper = $form_helper;

    // Get the entity type and field name from the plugin ID.
    [, $component_name] = explode(static::DERIVATIVE_SEPARATOR, $plugin_id, 4);
    $this->componentName = $component_name;
    $this->supportsInject = $supports_inject;

    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    // Get the entity type and field name from the plugin ID.
    [, $component_name] = explode(static::DERIVATIVE_SEPARATOR, $plugin_id, 4);
    $form_helper = new ClComponentForm(
      $component_name,
      $container->get('cl_block.form_generator'),
      $container->get(NoThemeComponentManager::class)
    );
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $form_helper,
      $container->get('logger.channel.cl_block'),
      $container->get(ComponentBlockRenderer::class),
      $container->get('module_handler')->moduleExists('cl_inject'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->getConfiguration();
    $context = $config['component']['data'] ?? [];
    $twig_blocks = $config['component']['twig_blocks'] ?? [];
    $variant = $config['component']['variant'] ?? NULL;
    return $this->renderer->buildFromId(
      $this->componentName,
      $variant,
      $context,
      $twig_blocks,
      array_filter($this->getContextValues()),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getPreviewFallbackString() {
    return new TranslatableMarkup(
      '"@component" CL Component',
      ['@component' => $this->componentName]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'label_display' => FALSE,
      'component' => [
        'label' => 'above',
        'variant' => '',
        'data' => [],
        'twig_blocks' => [],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $config = $this->getConfiguration();
    $form = $this->formHelper->buildForm($form, $form_state, $config);
    $form['component']['label'] = [
      '#type' => 'select',
      '#title' => $this->t('Label'),
      '#options' => [
        'above' => $this->t('Above'),
        'inline' => $this->t('Inline'),
        'hidden' => '- ' . $this->t('Hidden') . ' -',
        'visually_hidden' => '- ' . $this->t('Visually Hidden') . ' -',
      ],
      '#default_value' => $config['component']['label'],
      '#weight' => -1,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['component'] = $form_state->getValue('component');
  }

}
