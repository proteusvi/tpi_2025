<?php

namespace Drupal\cl_server\Controller;

use Drupal\sdc\ComponentPluginManager;
use Drupal\sdc\Exception\ComponentNotFoundException;
use Drupal\sdc\Exception\TemplateNotFoundException;
use Drupal\sdc\Plugin\Component;
use Drupal\sdc\Component\ComponentMetadata;
use Drupal\Core\Template\Attribute;
use Drupal\Core\State\StateInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Provides an endpoint for Storybook to query.
 *
 * @see https://github.com/storybookjs/storybook/tree/next/app/server
 */
class ServerEndpointController extends ControllerBase {

  /**
   * Kill-switch to avoid caching the page.
   *
   * @var \Drupal\Core\PageCache\ResponsePolicy\KillSwitch
   */
  private KillSwitch $cacheKillSwitch;

  /**
   * The discovery service.
   *
   * @var \Drupal\sdc\ComponentPluginManager
   */
  private ComponentPluginManager $pluginManager;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  private StateInterface $state;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  private TimeInterface $time;

  /**
   * Indicates if the site is operating in development mode.
   *
   * @var bool
   */
  private bool $developmentMode;

  /**
   * Creates an object.
   *
   * @param \Drupal\Core\PageCache\ResponsePolicy\KillSwitch $cache_kill_switch
   *   The cache kill switch.
   * @param \Drupal\sdc\ComponentPluginManager $plugin_manager
   *   The plugin manager.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(KillSwitch $cache_kill_switch, ComponentPluginManager $plugin_manager, StateInterface $state, TimeInterface $time, bool $development_mode) {
    $this->cacheKillSwitch = $cache_kill_switch;
    $this->pluginManager = $plugin_manager;
    $this->state = $state;
    $this->time = $time;
    $this->developmentMode = $development_mode;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    $cache_kill_switch = $container->get('page_cache_kill_switch');
    assert($cache_kill_switch instanceof KillSwitch);
    $plugin_manager = $container->get('plugin.manager.sdc');
    assert($plugin_manager instanceof ComponentPluginManager);
    $state = $container->get('state');
    assert($state instanceof StateInterface);
    $time = $container->get('datetime.time');
    assert($time instanceof TimeInterface);
    $development_mode = (bool) $container->getParameter('cl_server.development');
    return new static($cache_kill_switch, $plugin_manager, $state, $time, $development_mode);
  }

  /**
   * Render a Twig template from a Storybook component directory.
   */
  public function render(Request $request): array {
    try {
      $build = $this->generateRenderArray(
        $this->getComponent($request),
        $this->getArguments($request)
      );
    }
    catch (ComponentNotFoundException $e) {
      $build = [
        '#markup' => '<div class="messages messages--error"><h3>' . $this->t('Unable to find component') . '</h3>' . $this->t('Check that the module or theme containing the component is enabled and matches the stories file name. Message: %message', ['%message' => $e->getMessage()]) . '</div>',
      ];
    }
    if ($this->developmentMode) {
      $this->cacheKillSwitch->trigger();
      // Replace with the 'asset.query_string' service in drupal:^10.2.0.
      // @see https://www.drupal.org/node/3358337
      $query_string = base_convert(strval($this->time->getRequestTime()), 10, 36);
      $this->state->setMultiple([
        'system.css_js_query_string' => $query_string,
        'asset.css_js_query_string' => $query_string,
      ]);
    }
    return [
      '#attached' => ['library' => ['cl_server/attach_behaviors']],
      '#type' => 'container',
      '#cache' => ['max-age' => 0],
      // Magic wrapper ID to pull the HTML from.
      '#attributes' => ['id' => '___cl-wrapper'],
      'component' => $build,
    ];
  }

  /**
   * Gets the arguments.
   *
   * Retrieve the arguments from the query string if the request is a GET. If
   * the request is a POST, retrieve the arguments from the request body.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The inbound request.
   *
   * @return array
   *   The array of arguments.
   */
  private function getArguments(Request $request): array {
    $json = '[]';
    if ($request->getMethod() === 'GET') {
      $json = base64_decode($request->query->get('_params'), TRUE);
    }
    if ($request->getMethod() === 'POST') {
      $json = $request->getContent();
    }
    $args = Json::decode($json ?: '[]');
    return is_array($args) ? $args : [];
  }

  /**
   * Get the component based on the request object.
   *
   * @throws \Drupal\sdc\Exception\ComponentNotFoundException
   *   If the component cannot be found.
   */
  public function getComponent(Request $request): Component {
    $story_filename = $request->query->get('_storyFileName');
    if (!$story_filename) {
      throw new ComponentNotFoundException('Impossible to find a story with an empty story file name.');
    }
    $basename = basename($story_filename);
    [$machine_name] = explode('.', $basename);
    $provider = $this->findExtensionName($this->findStoryFile($story_filename));
    return $this->pluginManager->createInstance("$provider:$machine_name");
  }

  /**
   * Generates a render array to showcase the component with the expected
   * blocks.
   *
   * @param \Drupal\sdc\Plugin\Component $component
   *   The component.
   * @param array $context
   *   The template context.
   *
   * @return array
   *   The generated render array.
   */
  private function generateRenderArray(Component $component, array $context): array {
    $metadata = $component->metadata;
    // Try to convert a key-value attributes property into the Drupal object.
    if ($this->attributesPropNeedsUpcasting($context, $metadata)) {
      $context['attributes'] = new Attribute($context['attributes']);
    };
    $block_names = array_keys($metadata->slots);
    $slots = array_map(
      static fn (string $slot_str) => [
        '#type' => 'inline_template',
        '#template' => $slot_str,
        '#context' => $context,
      ],
      array_intersect_key($context, array_flip($block_names))
    );
    return [
      '#type' => 'component',
      '#component' => $component->getPluginId(),
      '#slots' => $slots,
      '#props' => array_diff_key($context, array_flip($block_names)),
    ];
  }

  /**
   * Checks if the provided attributes need to be upcasted.
   *
   * Returns TRUE when the component library sends an associative
   * array for the "attributes" property, and the metadata says
   * it should be a Drupal\Core\Template\Attribute.
   *
   * This is a special case, because of how common it is to have
   * Drupal attributes.
   *
   * @param array $context
   *   The template context.
   * @param \Drupal\sdc\Component\ComponentMetadata $metadata
   *   The component metadata.
   */
  private function attributesPropNeedsUpcasting(array $context, ComponentMetadata $metadata) {
    $properties = $metadata->schema['properties'] ?? [];
    $context_has_attributes = is_array($context['attributes'] ?? NULL);
    $metadata_has_attributes = ($properties['attributes']['type'][0] ?? NULL) === "Drupal\Core\Template\Attribute";
    return $context_has_attributes && $metadata_has_attributes;
  }

  /**
   * Finds the plugin ID from the story file name.
   *
   * The story file should be in the component directory, but storybook will
   * not process is from the Drupal docroot. This means we don't know what the
   * path is relative to.
   *
   * @param string $filename
   *   The filename.
   *
   * @return string
   *   The plugin ID.
   */
  private function findStoryFile(string $filename): ?string {
    if (empty($filename)) {
      return NULL;
    }
    if (file_exists($filename)) {
      return $filename;
    }
    $parts = explode(DIRECTORY_SEPARATOR, $filename);
    array_shift($parts);
    $filename = implode(DIRECTORY_SEPARATOR, $parts);
    return $this->findStoryFile($filename);
  }

  /**
   *
   */
  private function findExtensionName(string $path): ?string {
    if (empty($path)) {
      return NULL;
    }
    $path = dirname($path);
    $dir = basename($path);
    $info_file = $path . DIRECTORY_SEPARATOR . "$dir.info.yml";
    if (file_exists($info_file)) {
      return $dir;
    }
    return $this->findExtensionName($path);
  }

}
