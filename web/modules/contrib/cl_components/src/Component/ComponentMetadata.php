<?php

namespace Drupal\cl_components\Component;

use Drupal\cl_components\Exception\InvalidComponentException;
use Drupal\cl_components\Parser\ComponentPhpFile;
use JsonSchema\Validator;

/**
 * Component metadata.
 */
final class ComponentMetadata {

  public const COMPONENT_TYPE_ORGANISM = 'organism';

  public const COMPONENT_TYPE_MOLECULE = 'molecule';

  public const COMPONENT_TYPE_ATOM = 'atom';

  public const COMPONENT_STATUS_READY = 'READY';

  public const COMPONENT_STATUS_DEPRECATED = 'DEPRECATED';

  public const COMPONENT_STATUS_BETA = 'BETA';

  public const COMPONENT_STATUS_WIP = 'WIP';

  public const DEFAULT_DESCRIPTION = '- Description not available -';

  /**
   * The absolute path to the component directory.
   *
   * @var string
   */
  private string $path;

  /**
   * The available variants.
   *
   * @var array
   */
  private array $variants;

  /**
   * The component documentation.
   *
   * @var string
   */
  private string $documentation = '';

  /**
   * The component type.
   *
   * @var string
   */
  private string $componentType;

  /**
   * The status of the component.
   *
   * @var string
   */
  private string $status;

  /**
   * The machine name for the component.
   *
   * @var string
   */
  private string $machineName;

  /**
   * The component's name.
   *
   * @var string
   */
  private string $name;

  /**
   * The PNG path for the component thumbnail.
   *
   * @var string
   */
  private string $thumbnailPath = '';

  /**
   * The component group.
   *
   * @var string
   */
  private string $group;

  /**
   * The library dependencies.
   *
   * @var string[]
   */
  private array $libraryDependencies;

  /**
   * Schemas for the component.
   *
   * @var array[]
   *   The schemas.
   */
  private array $schemas = ['props' => []];

  /**
   * The component description.
   *
   * @var string
   */
  private string $description;

  /**
   * The hook names this component implements.
   *
   * @var string[]
   */
  private array $hooks;

  /**
   * The name safe for using in PHP functions.
   *
   * @var string
   */
  private string $safeName;

  /**
   * The full path to the PHP file.
   *
   * @var string
   */
  private string $phpPath;

  /**
   * The weight of the component.
   *
   * @var int
   */
  private int $weight;

  /**
   * ComponentMetadata constructor.
   *
   * @param array $metadata_info
   *   The metadata info.
   *
   * @throws \Drupal\cl_components\Exception\InvalidComponentException
   */
  public function __construct(array $metadata_info = []) {
    $path = $metadata_info['path'];
    // Make the absolute path, relative to the Drupal root.
    $app_root = rtrim(\Drupal::root(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (str_starts_with($path, $app_root)) {
      $path = substr($path, strlen($app_root));
    }
    $this->path = $path;
    $this->validateMetadataFile(Validator::arrayToObjectRecursive($metadata_info));

    $this->variants = $metadata_info['variants'] ?? [];
    $path_parts = explode('/', $path);
    $folder_name = end($path_parts);
    $this->machineName = $metadata_info['machineName'] ?? $folder_name;
    $this->name = $metadata_info['name'] ?? ucwords($this->machineName);
    $this->description = $metadata_info['description'] ?? static::DEFAULT_DESCRIPTION;
    $this->status = $metadata_info['status'] ?? static::COMPONENT_STATUS_WIP;
    $this->componentType = $metadata_info['componentType'] ?? static::COMPONENT_TYPE_ORGANISM;
    $this->libraryDependencies = $metadata_info['libraryDependencies'] ?? [];
    $this->documentation = $metadata_info['documentation'] ?? '';
    $this->weight = (int) ($metadata_info['weight'] ?? 0);

    // Load the PNG.
    $thumbnail_path = sprintf('%s/thumbnail.png', $path);
    if (file_exists($thumbnail_path)) {
      $this->thumbnailPath = $thumbnail_path;
    }

    $this->group = $metadata_info['group'] ?? 'All Components';

    // Save the schemas.
    $this->parseSchemaInfo($metadata_info);
    // Parse the hooks.
    $this->safeName = preg_replace('@[^a-z0-9]+@', '_', strtolower($this->machineName));
  }

  /**
   * Validates the metadata info.
   *
   * @param object $metadata_info
   *   The loaded metadata info.
   *
   * @throws \Drupal\cl_components\Exception\InvalidComponentException
   */
  private function validateMetadataFile(object $metadata_info): void {
    $validator = new Validator();
    $validator->validate(
      $metadata_info,
      (object) ['$ref' => 'file://' . dirname(__DIR__) . '/metadata.schema.json']
    );
    if (!$validator->isValid()) {
      $message_parts = array_map(
        fn(array $error): string => sprintf("[%s] %s", $error['property'], $error['message']),
        $validator->getErrors()
      );
      $message = implode("/n", $message_parts);
      throw new InvalidComponentException($message);
    }
  }

  /**
   * Parse the schema information.
   *
   * @param array $metadata_info
   *   The metadata information as decoded from the component definition file.
   *
   * @throws \Drupal\cl_components\Exception\InvalidComponentException
   */
  private function parseSchemaInfo(array $metadata_info): void {
    $default_schema = [
      'type' => 'object',
      'additionalProperties' => FALSE,
      'required' => [],
      'properties' => [],
    ];
    $this->schemas = $metadata_info['schemas'] ?? ['props' => $default_schema];
    if (($this->schemas['props']['type'] ?? 'object') !== 'object') {
      throw new InvalidComponentException('The schema for the props in the component metadata is invalid. The schema should be of type "object".');
    }
    if ($this->schemas['props']['additionalProperties'] ?? FALSE) {
      throw new InvalidComponentException('The schema for the props in the component metadata is invalid. Arbitrary additional properties are not allowed.');
    }
    $this->schemas['props']['additionalProperties'] = FALSE;
    // Save the props.
    $schema_props = $metadata_info['schemas']['props'] ?? $default_schema;
    foreach ($schema_props['properties'] ?? [] as $name => $schema) {
      // All props should also support "object" this allows deferring rendering
      // in Twig to the render pipeline.
      $type = $schema['type'] ?? '';
      if (!is_array($type)) {
        $type = [$type];
      }
      $schema['type'] = array_unique([...$type, 'object']);
      $this->schemas['props']['properties'][$name]['type'] = $type;
    }
    $this->schemas['named_blocks'] = $this->parseNamedBlockSchemaInfo();
  }

  /**
   * Parses the schema information for named blocks.
   *
   * @return array[]
   *   The schema.
   */
  private function parseNamedBlockSchemaInfo(): array {
    $default_template_path = sprintf(
      '%s%s%s.twig',
      $this->path,
      DIRECTORY_SEPARATOR,
      $this->machineName
    );
    $schema_info = [
      '' => $this->detectNamedBlocks($default_template_path),
    ];
    // Process each variant.
    return array_reduce(
      $this->variants,
      function (array $carry, string $variant) {
        $variant_template_path = sprintf(
          '%s%s%s--%s.twig',
          $this->path,
          DIRECTORY_SEPARATOR,
          $this->machineName,
          $variant
        );
        return [
          ...$carry,
          $variant => $this->detectNamedBlocks($variant_template_path),
        ];
      },
      $schema_info,
    );
  }

  /**
   * Detects the named blocks.
   *
   * @param string $template_path
   *   The template path.
   *
   * @return string[]
   *   The names of the Twig blocks
   */
  private function detectNamedBlocks(string $template_path): array {
    $template_contents = file_get_contents($template_path);
    $schema = [
      'type' => 'object',
      'additionalProperties' => FALSE,
      'properties' => [],
    ];
    $matches = [];
    $has_blocks = preg_match_all('@{%\s*block\s\s*([^\s.]*)\s*%}@', $template_contents, $matches);
    if (!$has_blocks) {
      return $schema;
    }
    $block_names = $matches[1] ?? [];
    $block_names = array_filter($block_names);
    $properties = array_reduce(
      $block_names,
      fn(array $carry, string $name) => [
        ...$carry,
        $name => [
          'type' => 'string',
          'title' => sprintf('Twig Block: %s', $name),
          'description' => sprintf(
            'The component %s (%s) contains a Twig block with the name %s. This accepts Twig code.',
            $this->name,
            $this->machineName,
            $name
          ),
        ],
      ],
      []
    );
    $schema['properties'] = $properties;
    return $schema;
  }

  /**
   * Gets the documentation.
   *
   * @return string
   *   The HTML documentation.
   */
  public function getDocumentation(): string {
    return $this->documentation;
  }

  /**
   * Gets the thumbnail path.
   *
   * @return string
   *   The path.
   */
  public function getThumbnailPath(): string {
    return $this->thumbnailPath;
  }

  /**
   * Normalizes the value object.
   *
   * @return array
   *   The normalized value object.
   */
  public function normalize(): array {
    return [
      'path' => $this->getPath(),
      'machineName' => $this->getMachineName(),
      'status' => $this->getStatus(),
      'componentType' => $this->getComponentType(),
      'name' => $this->getName(),
      'group' => $this->getGroup(),
      'variants' => $this->getVariants(),
      'libraryDependencies' => $this->getLibraryDependencies(),
    ];
  }

  /**
   * Gets the path.
   *
   * @return string
   *   The path.
   */
  public function getPath(): string {
    return $this->path;
  }

  /**
   * Gets the machine name.
   *
   * @return string
   *   The machine name.
   */
  public function getMachineName(): string {
    return $this->machineName;
  }

  /**
   * Gets the status.
   *
   * @return string
   *   The status.
   */
  public function getStatus(): string {
    return $this->status;
  }

  /**
   * Gets the component type.
   *
   * @return string
   *   The component type.
   */
  public function getComponentType(): string {
    return $this->componentType;
  }

  /**
   * Gets the name.
   *
   * @return string
   *   The name.
   */
  public function getName(): string {
    return $this->name;
  }

  /**
   * Gets the group.
   *
   * @return string
   *   The group.
   */
  public function getGroup(): string {
    return $this->group;
  }

  /**
   * Gets the list of variants.
   *
   * @return string[]
   *   The variants.
   */
  public function getVariants(): array {
    return $this->variants;
  }

  /**
   * Gets the library dependencies.
   *
   * @return string[]
   *   The dependencies.
   */
  public function getLibraryDependencies(): array {
    return $this->libraryDependencies;
  }

  /**
   * Gets the schemas.
   *
   * @return array[][]
   *   The schemas.
   */
  public function getSchemas(): array {
    return $this->schemas;
  }

  /**
   * Get the description.
   *
   * @return string
   *   The description.
   */
  public function getDescription(): string {
    return $this->description;
  }

  /**
   * Gets the machine name safe to use in PHP function names.
   *
   * @return string
   */
  public function getSafeName(): string {
    return $this->safeName;
  }

  /**
   * Gets the hook names.
   *
   * @return string[]
   *   The hook names.
   *
   * @throws \Drupal\cl_components\Exception\ComponentSyntaxException
   */
  public function getHooks(): array {
    if (isset($this->hooks)) {
      return $this->hooks;
    }
    $php_file = new ComponentPhpFile($this->path, $this->machineName, $this->safeName);
    $this->phpPath = $php_file->path;
    $this->hooks = $php_file->hasPhpFile() ? $php_file->parseHooks() : [];
    return $this->hooks;
  }

  /**
   * The path to the PHP file.
   *
   * @return string
   *   The path to the PHP file.
   */
  public function getPhpPath(): string {
    return $this->phpPath;
  }

  /**
   * Gets the component weight when more than one by ID exist.
   *
   * @return int
   *   The weight.
   */
  public function getWeight(): int {
    return $this->weight;
  }

}
