<?php

namespace Drupal\cl_components;

use Drupal\cl_components\Exception\ComponentNotFoundException;
use Drupal\cl_components\Plugin\Component;
use Drupal\cl_components\Plugin\Discovery\DirectoryWithMetadataPluginDiscovery;
use Drupal\Component\Discovery\YamlDirectoryDiscovery;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Plugin\Factory\ContainerFactory;
use Drupal\Core\Theme\ThemeManagerInterface;

/**
 * Defines a plugin manager to deal with cl_components.
 *
 * Modules and themes can create components by adding a folder under
 * MODULENAME/templates/components/my-component/my-component.cl_component.yml.
 *
 * @see plugin_api
 */
class ComponentPluginManager extends DefaultPluginManager {

  /**
   * The theme handler.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected ThemeHandlerInterface $themeHandler;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected ThemeManagerInterface $themeManager;

  /**
   * The component negotiatior.
   *
   * @var \Drupal\cl_components\ComponentNegotiator
   */
  protected ComponentNegotiator $componentNegotiator;

  /**
   * The component plugin instance cache.
   *
   * @var Component[]
   */
  protected array $componentPluginCacheEntries = [];

  /**
   * {@inheritdoc}
   */
  protected $defaults = [
    'class' => Component::class,
  ];

  /**
   * Constructs ClComponentPluginManager object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler
   *   The theme handler.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $theme_manager
   *   The theme manager.
   * @param \Drupal\cl_components\ComponentNegotiator $component_negotiator
   *   The component negotiator.
   */
  public function __construct(ModuleHandlerInterface $module_handler, ThemeHandlerInterface $theme_handler, CacheBackendInterface $cache_backend, ConfigFactoryInterface $config_factory, ThemeManagerInterface $theme_manager, ComponentNegotiator $component_negotiator) {
    $this->factory = new ContainerFactory($this);
    $this->moduleHandler = $module_handler;
    $this->themeHandler = $theme_handler;
    $this->configFactory = $config_factory;
    $this->themeManager = $theme_manager;
    $this->componentNegotiator = $component_negotiator;
    $this->alterInfo('cl_component_info');
    $this->setCacheBackend($cache_backend, 'cl_component_plugins');
  }

  /**
   * Creates an instance.
   *
   * @throws \Drupal\cl_components\Exception\ComponentNotFoundException
   *
   * @internal
   */
  public function createInstance($plugin_id, array $configuration = []): Component {
    // Add default configuration.
    $default_configuration = $this->getDefaultConfiguration();
    try {
      $instance = parent::createInstance(
        $plugin_id,
        array_merge($default_configuration, $configuration)
      );
      if (!$instance instanceof Component) {
        throw new ComponentNotFoundException(sprintf(
          'Unable to find component "%s" in the component repository.',
          $plugin_id,
        ));
      }
      return $instance;
    }
    catch (PluginException | PluginException $e) {
      // Cast the PluginNotFound to a more specific exception.
      $message = sprintf(
        'Unable to find component "%s" in the component repository. [%s]',
        $plugin_id,
        $e->getMessage()
      );
      throw new ComponentNotFoundException($message, $e->getCode(), $e);
    }
  }

  /**
   * Creates instance catching exceptions.
   */
  public function createInstanceAndCatch(string $plugin_id): ?Component {
    try {
      return $this->createInstance($plugin_id);
    }
    catch (ComponentNotFoundException | PluginException $e) {
      return NULL;
    }
  }

  /**
   * Gets a component for rendering.
   *
   * @param string $machine_name
   *   The machine name.
   *
   * @return \Drupal\cl_components\Plugin\Component
   *   The component.
   *
   * @throws \Drupal\cl_components\Exception\ComponentNotFoundException
   *
   * @internal
   */
  public function find(string $machine_name): Component {
    // Check if the component plugin instance was already created.
    if (isset($this->componentPluginCacheEntries[$machine_name])) {
      return $this->componentPluginCacheEntries[$machine_name];
    }

    $definitions = $this->getDefinitions();
    if (empty($definitions)) {
      throw new ComponentNotFoundException('Unable to find any component definition.');
    }
    $instance = $this->createInstance(
      $this->componentNegotiator->negotiate($machine_name, $definitions)
    );

    // Cache the component plugin instance.
    $this->componentPluginCacheEntries[$machine_name] = $instance;

    return $instance;
  }

  /**
   * Gets all components.
   *
   * @return \Drupal\cl_components\Plugin\Component[]
   *
   * @internal
   */
  public function getAllComponents(): array {
    $plugin_ids = array_keys($this->getDefinitions());
    return array_values(array_filter(array_map(
      [$this, 'createInstanceAndCatch'],
      $plugin_ids
    )));
  }

  /**
   * Creates the library declaration array from a component.
   *
   * @param \Drupal\cl_components\Plugin\Component $component
   *   The component info.
   *
   * @return array
   *   The library for the Library API.
   *
   * @internal
   */
  public function libraryFromComponent(Component $component): array {
    $library = [];
    $styles = $component->getStyles();
    if (!empty($styles)) {
      $library['css'] = [
        'component' => array_reduce($styles, function (array $css, string $file) {
          return array_merge($css, [$file => []]);
        }, []),
      ];
    }
    $scripts = $component->getScripts();
    if (!empty($scripts)) {
      $library['js'] = array_reduce($scripts, function (array $js, string $file) {
        return array_merge($js, [$file => []]);
      }, []);
    }

    $library['dependencies'] = array_merge(
    // Ensure that 'core/drupal' is always present.
      ['core/drupal'],
      $component->getMetadata()->getLibraryDependencies()
    );
    return $library;
  }

  /**
   * {@inheritdoc}
   */
  protected function getDiscovery() {
    if (!isset($this->discovery)) {
      $directories = $this->getScanDirectories();
      $this->discovery = new DirectoryWithMetadataPluginDiscovery($directories, 'cl_component');
    }
    return $this->discovery;
  }

  /**
   * {@inheritdoc}
   */
  protected function providerExists($provider) {
    return $this->moduleHandler->moduleExists($provider) || $this->themeHandler->themeExists($provider);
  }

  /**
   * {@inheritdoc}
   */
  protected function alterDefinitions(&$definitions) {
    // Save in the definition weather this is a module or a theme. This is
    // important because when creating the plugin instance (the Component
    // object) we'll need to negotiate based on the active theme.
    foreach ($definitions as $key => $definition) {
      $definitions[$key]['extension_type'] = $this->moduleHandler->moduleExists($definition['provider'] ?? '')
        ? ExtensionType::Module
        : ExtensionType::Theme;
      $metadata_path = $definition[YamlDirectoryDiscovery::FILE_KEY];
      $component_directory = dirname($metadata_path);
      $definitions[$key]['path'] = $component_directory;
      $definitions[$key]['libraryFiles'] = [
        'css' => $this->getDiscovery()
          ->findAssets($component_directory, 'css', TRUE),
        'js' => $this->getDiscovery()
          ->findAssets($component_directory, 'js', TRUE),
      ];
      $templates = $this->getDiscovery()
        ->findAssets($component_directory, 'twig');
      $definitions[$key]['templates'] = $this->getDiscovery()
        ->discoverTemplates($component_directory);
      $definitions[$key]['documentation'] = 'No documentation found. Add a README.md in your component directory and install the package "league/commonmark" using composer.';
      $documentation_path = sprintf('%s/README.md', dirname($metadata_path));
      if (class_exists('\League\CommonMark\CommonMarkConverter') && file_exists($documentation_path)) {
        $documentation_md = file_get_contents($documentation_path);
        // phpcs:ignore Drupal.Classes.FullyQualifiedNamespace.UseStatementMissing
        $converter = new \League\CommonMark\CommonMarkConverter();
        $definitions[$key]['documentation'] = $converter->convert($documentation_md);
      }
    }
    // If the metadata file is *.component.yml then the first bit should match
    // the machine name.
    $invalid_definition_ids = array_keys(array_filter(
      $definitions,
      static fn(array $definition) => str_ends_with($definition[YamlDirectoryDiscovery::FILE_KEY] ?? '', '.component.yml')
        && !str_ends_with($definition[YamlDirectoryDiscovery::FILE_KEY] ?? '', DIRECTORY_SEPARATOR . $definition['machineName'] . '.component.yml')
    ));
    $definitions = array_diff_key($definitions, array_flip($invalid_definition_ids));
    // Finally, allow hooks to alter the component definitions.
    parent::alterDefinitions($definitions);
  }

  /**
   * Get the list of directories to scan.
   *
   * @return string[]
   *   The directories.
   */
  private function getScanDirectories(): array {
    $extension_directories = [
      ...$this->moduleHandler->getModuleDirectories(),
      ...$this->themeHandler->getThemeDirectories(),
    ];
    return array_map(
      static fn(string $path) => rtrim(sprintf(
        '%s%s%s',
        rtrim($path, DIRECTORY_SEPARATOR),
        DIRECTORY_SEPARATOR,
        implode(DIRECTORY_SEPARATOR, ['templates', 'components']),
      ), DIRECTORY_SEPARATOR),
      $extension_directories
    );
  }

  /**
   * The default configuration for all components.
   *
   * @return array
   */
  protected function getDefaultConfiguration(): array {
    $settings = $this->configFactory->get('cl_components.settings');
    return [
      'enable_debug_mode' => $settings->get('debug') ?? FALSE,
    ];
  }

}
