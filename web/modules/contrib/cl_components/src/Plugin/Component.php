<?php

namespace Drupal\cl_components\Plugin;

use Drupal\cl_components\Component\ComponentMetadata;
use Drupal\cl_components\Exception\InvalidComponentException;
use Drupal\cl_components\Exception\InvalidComponentHookException;
use Drupal\cl_components\Exception\TemplateNotFoundException;
use Drupal\Component\Utility\Html;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Template\Attribute;

/**
 * Simple value object that contains information about the component.
 */
class Component extends PluginBase {

  private const TEMPLATE_VARIANT_SEPARATOR = '--';

  /**
   * The styles to include in the component.
   *
   * @var string[]
   */
  private array $styles;

  /**
   * The javascript objects to include on the component.
   *
   * @var string[]
   */
  private array $scripts;

  /**
   * The component's metadata.
   *
   * This includes the available variants, and documentation.
   *
   * @var \Drupal\cl_components\Component\ComponentMetadata
   */
  private ComponentMetadata $metadata;

  /**
   * The component ID.
   *
   * @var string
   */
  private string $id;

  /**
   * The Twig templates in the repository.
   *
   * @var string[]
   */
  private array $templates;

  /**
   * Is debug mode on?
   *
   * @var bool
   */
  private bool $debugMode;

  /**
   * Component constructor.
   *
   * @throws \Drupal\cl_components\Exception\InvalidComponentException
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->styles = $plugin_definition['libraryFiles']['css'] ?? [];
    $this->templates = $plugin_definition['templates'] ?? [];
    $this->scripts = $plugin_definition['libraryFiles']['js'] ?? [];
    $this->id = $plugin_definition['machineName'];
    $this->metadata = new ComponentMetadata($plugin_definition);
    $this->debugMode = $configuration['enable_debug_mode'] ?? FALSE;
    $this->validate();
  }

  /**
   * Checks if the component is in debug mode.
   *
   * @return bool
   *   TRUE if it is in debug mode.
   */
  public function isDebugMode(): bool {
    return $this->debugMode;
  }

  /**
   * Validates the data for the component object.
   *
   * @throws \Drupal\cl_components\Exception\InvalidComponentException
   *   If the component is invalid.
   */
  private function validate() {
    $num_main_templates = count($this->getMainTemplates());
    if ($num_main_templates === 0) {
      $message = sprintf('Unable to find main template %s.twig or any of its variants.', $this->getId());
      throw new InvalidComponentException($message);
    }
    if (strpos($this->getId(), '/') !== FALSE) {
      $message = sprintf('Component ID cannot contain slashes: %s', $this->getId());
      throw new InvalidComponentException($message);
    }
  }

  /**
   * The main templates for the component.
   *
   * @return string[]
   *   The template names.
   */
  public function getMainTemplates(): array {
    return array_filter($this->getTemplates(), function (string $template) {
      $regexp = sprintf('%s(%s[^\.]+)?\.(twig)', $this->getId(), static::TEMPLATE_VARIANT_SEPARATOR);
      return (bool) preg_match('/' . $regexp . '/', $template);
    });
  }

  /**
   * The template names.
   *
   * @return string[]
   *   The names.
   */
  public function getTemplates(): array {
    return $this->templates;
  }

  /**
   * The ID.
   *
   * @return string
   *   The ID.
   */
  public function getId(): string {
    return $this->id;
  }

  /**
   * The styles.
   *
   * @return string[]
   *   The stylesheet paths.
   */
  public function getStyles(): array {
    return $this->styles;
  }

  /**
   * The JS.
   *
   * @return string[]
   *   The script paths.
   */
  public function getScripts(): array {
    return $this->scripts;
  }

  /**
   * Get the template name for the selected variant.
   *
   * @param string $variant
   *   The template variant.
   *
   * @return string
   *   The name of the template.
   *
   * @throws \Drupal\cl_components\Exception\TemplateNotFoundException
   */
  public function getTemplateName(string $variant = ''): string {
    $filename = sprintf('%s%s%s.twig', $this->getId(), static::TEMPLATE_VARIANT_SEPARATOR, $variant);
    // If it cannot find the variant, fall back to the base.
    if (!in_array($filename, $this->getMainTemplates())) {
      $filename = sprintf('%s.twig', $this->getId());
    }
    if (!in_array($filename, $this->getMainTemplates())) {
      $message = sprintf('Unable to find template %s.', $filename);
      throw new TemplateNotFoundException($message);
    }
    return $filename;
  }

  /**
   * The auto-computed library name.
   *
   * @return string
   *   The library name.
   */
  public function getLibraryName(): string {
    $library_id = $this->getPluginId();
    $library_id = str_replace(':', '--', $library_id);
    return sprintf('cl_components/%s', $library_id);
  }

  /**
   * The variants.
   *
   * @return array
   *   The available variants.
   */
  public function getVariants(): array {
    return $this->metadata->getVariants();
  }

  /**
   * Gets the component metadata.
   *
   * @return \Drupal\cl_components\Component\ComponentMetadata
   *   The component metadata.
   */
  public function getMetadata(): ComponentMetadata {
    return $this->metadata;
  }

  /**
   * Invokes a CL Component hook.
   *
   * @param string $hook
   *   The hook to invoke.
   * @param array $args
   *   The arguments for the hook.
   *
   * @return mixed
   *   The value returned by the component hook.
   *
   * @throws \Drupal\cl_components\Exception\ComponentSyntaxException
   * @throws \Drupal\cl_components\Exception\InvalidComponentHookException
   */
  public function invokeHook(string $hook, array $args): mixed {
    $metadata = $this->getMetadata();
    if (!in_array($hook, $metadata->getHooks())) {
      $message = sprintf('The requested hook "%s" is not supported by component "%s".', $hook, $this->getId());
      throw new InvalidComponentHookException($message);
    }
    $func_name = sprintf('cl_component_%s_%s', $metadata->getSafeName(), $hook);
    if (!function_exists($func_name)) {
      require_once $this->getMetadata()->getPhpPath();
    }
    return $func_name(...[...$args, $this]);
  }

  /**
   * Calculates additional context for this template.
   *
   * @param string $variant
   *   The variant.
   *
   * @return array
   *   The additional context to inject to component templates.
   */
  public function additionalRenderContext($variant = ''): array {
    $metadata = $this->getMetadata();
    $status = $metadata->getStatus();
    $classes = array_map([Html::class, 'cleanCssIdentifier'], [
      'cl-component',
      'cl-component--' . $this->getId(),
      'cl-component--' . $metadata->getComponentType(),
      'cl-component--' . $status,
    ]);
    $classes = array_map('strtolower', $classes);
    $attributes = [
      'class' => $classes,
      'data-cl-component-id' => $this->getId(),
      'data-cl-component-variant' => $variant,
    ];
    // If debug mode is enabled, then add a class.
    if ($this->debugMode) {
      $attributes['class'][] = 'cl-component--debug';
      $args = [
        '%id' => $this->getPluginId(),
        '%name' => $metadata->getName(),
        '%variant' => $variant ?: '- none -',
        '%status' => $status,
        '%description' => $metadata->getDescription(),
      ];
      $title = $this->t(
        "Component: \"%name\" (%id).\nVariant: %variant\nStatus: %status\nDescription: %description",
        $args
      );
      $attributes['title'] = $attributes['title'] ?? $title;
    }
    return [
      'clAttributes' => new Attribute($attributes),
      'clMeta' => $metadata->normalize(),
      'variant' => $variant,
    ];
  }

}
