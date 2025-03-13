<?php

namespace Drupal\cl_components\Twig;

use Drupal\cl_components\ComponentPluginManager;
use Drupal\cl_components\Exception\ComponentNotFoundException;
use Drupal\cl_components\Exception\TemplateNotFoundException;
use Drupal\cl_components\Plugin\Component;
use Drupal\Component\Discovery\YamlDirectoryDiscovery;
use Drupal\Core\Render\RendererInterface;
use Twig\Error\LoaderError;
use Twig\Loader\LoaderInterface;
use Twig\Source;

/**
 * Lets you load templates using the component ID.
 */
class TwigComponentLoader implements LoaderInterface {

  /**
   * The plugin manager.
   *
   * @var \Drupal\cl_components\ComponentPluginManager
   */
  protected ComponentPluginManager $pluginManager;

  /**
   * Checks if Twig is in debug mode.
   *
   * @var bool
   */
  private bool $twigDebug;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  private RendererInterface $renderer;

  /**
   * Constructs a new ComponentLoader object.
   *
   * @param \Drupal\cl_components\ComponentPluginManager $plugin_manager
   *   The plugin manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param array|null $twig_config
   *   The twig configuration.
   */
  public function __construct(ComponentPluginManager $plugin_manager, RendererInterface $renderer, ?array $twig_config) {
    $this->pluginManager = $plugin_manager;
    $twig_config = $twig_config ?: [];
    $this->twigDebug = (bool) ($twig_config['debug'] ?? FALSE);
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Twig\Error\LoaderError
   *   Thrown if a template matching $name cannot be found.
   */
  protected function findTemplate($name, $throw = TRUE) {
    $path = $name;
    try {
      $component = $this->parseIdAndLoadComponent($name);
      $variant = $this->parseVariantFromName($name);
      $template = $component->getTemplateName($variant ?? '');
      $path = sprintf(
        '%s%s%s',
        $component->getMetadata()->getPath(),
        DIRECTORY_SEPARATOR,
        $template
      );
    }
    catch (ComponentNotFoundException | TemplateNotFoundException  $e) {
      if ($throw) {
        throw new LoaderError($e->getMessage(), $e->getCode(), $e);
      }
    }
    if ($path || !$throw) {
      return $path;
    }

    throw new LoaderError(sprintf('Unable to find template "%s" in the components registry.', $name));
  }

  /**
   * {@inheritdoc}
   */
  public function exists($name): bool {
    if (!preg_match('/^[a-zA-Z][a-zA-Z0-9:_-]*[a-zA-Z0-9]?$/', $name)) {
      return FALSE;
    }
    try {
      $this->parseIdAndLoadComponent($name);
      return TRUE;
    }
    catch (ComponentNotFoundException $e) {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceContext($name): Source {
    try {
      $component = $this->parseIdAndLoadComponent($name);
      $variant = $this->parseVariantFromName($name);
      $path = $component->getMetadata()
        ->getPath() . DIRECTORY_SEPARATOR . $component->getTemplateName($variant);
    }
    catch (ComponentNotFoundException | TemplateNotFoundException $e) {
      return new Source('', $name, '');
    }
    $plugin_id = $component->getPluginId();
    $code = file_get_contents($path);
    $library_name = $component->getLibraryName();
    $code = "{{ attach_library('$library_name') }}" . PHP_EOL
      . "{{ cl_components_additional_context('" . addcslashes($component->getId(), "'") . "', '" . addcslashes($variant ?: '', "'") . "', " . (int) ($this->twigDebug && $component->isDebugMode()) . ") }}"
      . PHP_EOL . $code;
    if ($component->isDebugMode()) {
      $code = "{{ attach_library('cl_components/cl_debug') }}" . PHP_EOL . $code;
    }
    if ($this->twigDebug) {
      $code = "{# start cl_component $plugin_id #}" . PHP_EOL
        . "<!-- start cl_component $plugin_id -->" . PHP_EOL
        . $code . PHP_EOL
        . "<!-- end cl_component $plugin_id -->" . PHP_EOL
        . "{# end cl_component $plugin_id #}" . PHP_EOL;
    }
    return new Source($code, $name, $path);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheKey($name): string {
    try {
      $component = $this->parseIdAndLoadComponent($name);
    }
    catch (ComponentNotFoundException | TemplateNotFoundException $e) {
      throw new LoaderError('Unable to find component');
    }
    return implode('--', array_filter([
      'cl-component',
      $name,
      $this->parseVariantFromName($name),
      $component->getPluginDefinition()['provider'] ?? '',
    ]));
  }

  /**
   * {@inheritdoc}
   */
  public function isFresh($name, $time): bool {
    $file_is_fresh = static fn(string $path) => filemtime($path) < $time;
    try {
      $component = $this->parseIdAndLoadComponent($name);
    }
    catch (ComponentNotFoundException | TemplateNotFoundException $e) {
      throw new LoaderError('Unable to find component');
    }
    // If any of the templates, or the component definition, are fresh. Then the
    // component is fresh.
    $metadata_path = $component->getPluginDefinition()[YamlDirectoryDiscovery::FILE_KEY];
    if ($file_is_fresh($metadata_path)) {
      return TRUE;
    }
    return array_reduce(
      array_map(
        static fn(string $name) => $component->getMetadata()
          ->getPath() . DIRECTORY_SEPARATOR . $name,
        $component->getTemplates()
      ),
      static fn(bool $fresh, string $path) => $fresh || $file_is_fresh($path),
      FALSE
    );
  }

  /**
   * Parse ID and variant from the template key.
   *
   * @param string $name
   *   The template name as provided in the include/embed.
   *
   * @return \Drupal\cl_components\Plugin\Component
   *   The component.
   *
   * @throws \Drupal\cl_components\Exception\ComponentNotFoundException
   */
  private function parseIdAndLoadComponent(string $name): Component {
    // First check if we can parse the prefix and variant from the name.
    $id = $name;
    $variant = '';
    if (str_contains($name, '--')) {
      [$id, $variant] = explode('--', $id);
    }
    $component = str_contains($id, ':')
      ? $this->pluginManager->createInstance($id)
      : $this->pluginManager->find($id);
    $available_variants = $component->getVariants();
    if (!empty($variant) && !in_array($variant, $available_variants, TRUE)) {
      $message = sprintf(
        'Variant "%s" not found in component "%s".',
        $variant,
        $component->getMetadata()->getName()
      );
      throw new ComponentNotFoundException($message);
    }
    return $component;
  }

  /**
   * Parses the variant from the name.
   *
   * @param string $name
   *   The name.
   *
   * @return string
   *   The parsed variant.
   */
  private function parseVariantFromName(string $name): string {
    $matches = [];
    preg_match('/^.*--(.*)$/', $name, $matches);
    return $matches[1] ?? '';
  }

}
