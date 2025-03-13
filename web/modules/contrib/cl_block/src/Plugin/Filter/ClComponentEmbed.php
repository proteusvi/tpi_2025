<?php

namespace Drupal\cl_block\Plugin\Filter;

use Drupal\cl_block\Form\SettingsForm;
use Drupal\cl_block\Render\ComponentBlockRenderer;
use Drupal\cl_editorial\NoThemeComponentManager;
use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Markup;
use Drupal\embed\DomHelperTrait;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a filter to display embedded entities based on data attributes.
 *
 * @Filter(
 *   id = "cl_block",
 *   title = @Translation("Embed CL Components"),
 *   description = @Translation("Embeds CL Components using data attributes: data-component-id, data-component-variant and data-component-settings. Should usually run as the last filter, since it does not contain user input."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_REVERSIBLE,
 *   weight = 100,
 *   settings = {
 *     "filters" = {
 *       "types" = {Drupal\cl_components\Component\ComponentMetadata::COMPONENT_TYPE_ORGANISM},
 *       "statuses" = {
 *         Drupal\cl_components\Component\ComponentMetadata::COMPONENT_STATUS_READY,
 *         Drupal\cl_components\Component\ComponentMetadata::COMPONENT_STATUS_BETA,
 *       },
 *       "forbidden" = {},
 *       "allowed" = {},
 *     },
 *   },
 * )
 */
class ClComponentEmbed extends FilterBase implements ContainerFactoryPluginInterface {

  use DomHelperTrait;

  /**
   * The renderer service.
   *
   * @var \Drupal\cl_block\Render\ComponentBlockRenderer
   */
  readonly protected ComponentBlockRenderer $renderer;

  /**
   * The manager for components.
   *
   * @var \Drupal\cl_editorial\NoThemeComponentManager
   */
  readonly protected NoThemeComponentManager $componentManager;

  /**
   * Constructs a ElementEmbed object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\cl_block\Render\ComponentBlockRenderer $renderer
   *   The renderer.
   * @param \Drupal\cl_editorial\NoThemeComponentManager $component_manager
   *   The component manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ComponentBlockRenderer $renderer, NoThemeComponentManager $component_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->renderer = $renderer;
    $this->componentManager = $component_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $renderer = $container->get(ComponentBlockRenderer::class);
    assert($renderer instanceof ComponentBlockRenderer);
    $component_manager = $container->get(NoThemeComponentManager::class);
    assert($component_manager instanceof NoThemeComponentManager);
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $renderer,
      $component_manager,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $result = new FilterProcessResult($text);
    if (!strpos($text, 'cl-component') !== FALSE) {
      return $result;
    }

    $dom = Html::load($text);
    $xpath = new \DOMXPath($dom);

    $xpath_query = '//cl-component[@data-component-id and @data-component-settings]';
    foreach ($xpath->query($xpath_query) as $node) {
      assert($node instanceof \DOMElement);
      $element_output = '';
      $data = $this->getNodeAttributesAsArray($node);
      $component_id = $data['data-component-id'] ?? '';
      $collected_attachments = [];
      $element_output = $this->renderer->renderComponentFromId(
        $component_id,
        $data['data-component-settings']['variant'] ?? '',
        $data['data-component-settings']['data'] ?? [],
        $data['data-component-settings']['twig_blocks'] ?? [],
        // @todo how do we derive token replacement data?
        []
      );
      $result->addAttachments($collected_attachments);
      $this->replaceNodeContent($node, $element_output instanceof Markup ? (string) $element_output : $element_output);
    }

    $result->setProcessedText(Html::serialize($dom));
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function tips($long = FALSE) {
    return $this->t('You can configure and embed CL Components.');
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    SettingsForm::buildSettingsForm(
      $form,
      $form_state,
      $this->componentManager,
      $this->settings['filters'] ?? [],
      [...$form['#parents'], 'filters'],
      $this->t('Set a combination of filters to control the list of components that will be available after clicking the embed button. This configuration is only used for buttons that allow component selection at embed time.')
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    parent::setConfiguration($configuration);
    $clean_array = fn(array $input): array => array_values(array_filter($input));
    // Clean-up settings.
    $filters = $this->settings['filters'];
    $filters['types'] = $clean_array($filters['types']);
    $filters['statuses'] = $clean_array($filters['statuses']);
    $filters['forbidden'] = [];
    $filters['allowed'] = [];
    $refine = $filters['refine'] ?? NULL;
    if ($refine) {
      unset($filters['refine']);
      $filters['forbidden'] = $clean_array($refine['forbidden'] ?? []);
      $filters['allowed'] = $clean_array($refine['allowed'] ?? []);
    }
    $this->settings['filters'] = $filters;
    return $this;
  }

}
