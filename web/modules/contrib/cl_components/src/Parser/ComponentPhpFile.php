<?php

namespace Drupal\cl_components\Parser;

use Drupal\cl_components\Exception\ComponentSyntaxException;

/**
 *
 */
class ComponentPhpFile {

  public const SUPPORTED_CL_HOOKS = ['form_alter', 'library_info_alter'];

  /**
   * The path to the PHP file.
   *
   * @var string
   */
  public readonly string $path;

  /**
   * The component's safe name.
   *
   * @var string
   */
  private readonly string $safeName;

  /**
   * Creates a ComponentPhpFile object.
   *
   * @param string $component_path
   *   The path to the component folder.
   * @param string $machine_name
   *   The component machine name.
   * @param string $safe_name
   *   The component machine name in snake case.
   */
  public function __construct(string $component_path, string $machine_name, string $safe_name) {
    $this->path = sprintf(
      '%s%s%s%s%s.php',
      \Drupal::root(),
      DIRECTORY_SEPARATOR,
      $component_path,
      DIRECTORY_SEPARATOR,
      $machine_name
    );
    $this->safeName = $safe_name;
  }

  /**
   * Checks if the file exists.
   *
   * @return bool
   *   TRUE if the file exits, FALSE otherwise.
   */
  public function hasPhpFile(): bool {
    return file_exists($this->path);
  }

  /**
   * Populate the hooks property.
   *
   * We need to be careful about performance implications.
   *
   * @return string[]
   *   The hook names this component implements.
   *
   * @throws \Drupal\cl_components\Exception\ComponentSyntaxException
   *   When the component implements invalid hooks.
   */
  public function parseHooks(): array {
    $resource = fopen($this->path, 'rb');
    $hooks = [];
    while (!feof($resource)) {
      $line = fgets($resource);
      if (!str_starts_with($line, 'function cl_component_' . $this->safeName)) {
        continue;
      }
      $pattern = '@^ *function cl_component_' . $this->safeName . '_([^\)]*) *\(.*$@';
      $hook = trim(preg_replace($pattern, '$1', $line));
      if (!in_array($hook, static::SUPPORTED_CL_HOOKS)) {
        $message = sprintf('Invalid hook implementation "%s". Allowed hooks are "%s".', $hook, implode(', ', static::SUPPORTED_CL_HOOKS));
        throw new ComponentSyntaxException($message);
      }
      $hooks[] = $hook;
    }
    fclose($resource);
    if (!empty($hooks)) {
      require_once $this->path;
    }
    return array_filter(
      $hooks,
      fn (string $hook) => function_exists(sprintf('cl_component_%s_%s', $this->safeName, $hook))
    );
  }

}
