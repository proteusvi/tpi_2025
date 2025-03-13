<?php

namespace Drupal\cl_components\Plugin\Discovery;

use Drupal\Core\Plugin\Discovery\YamlDiscovery;

/**
 * Discover directories that contain a specific metadata file.
 */
class DirectoryWithMetadataPluginDiscovery extends YamlDiscovery {

  /**
   * Constructs a YamlDirectoryDiscovery object.
   *
   * @param array $directories
   *   An array of directories to scan, keyed by the provider. The value can
   *   either be a string or an array of strings. The string values should be
   *   the path of a directory to scan.
   * @param string $file_cache_key_suffix
   *   The file cache key suffix. This should be unique for each type of
   *   discovery.
   * @param string $key
   *   (optional) The key contained in the discovered data that identifies it.
   *   Defaults to 'id'.
   */
  public function __construct(array $directories, $file_cache_key_suffix, string $key = 'machineName') {
    // Intentionally does not call parent constructor as this class uses a
    // different YAML discovery.
    $this->discovery = new DirectoryWithMetadataDiscovery($directories, $file_cache_key_suffix, $key);
  }

  /**
   * Finds twig templates.
   *
   * @param string $component_directory
   *   The component directory for the plugin.
   *
   * @return string[]
   *   Filenames.
   */
  public function discoverTemplates(string $component_directory): array {
    // Use FilesystemIterator to not iterate over the . and .. directories.
    $flags = \FilesystemIterator::KEY_AS_PATHNAME
      | \FilesystemIterator::CURRENT_AS_FILEINFO
      | \FilesystemIterator::SKIP_DOTS;
    $directory_iterator = new \RecursiveDirectoryIterator($component_directory, $flags);
    $filter = new RegexRecursiveFilterIterator($directory_iterator, '/^.+\\.twig$/i');
    $files = new \RecursiveIteratorIterator($filter, \RecursiveIteratorIterator::LEAVES_ONLY, $flags);
    $output = [];
    $forbidden_extension = '.html.twig';
    foreach ($files as $file_info) {
      assert($file_info instanceof \SplFileInfo);
      $filename = $file_info->getFilename();
      $pos = strpos($filename, $forbidden_extension);
      if ($pos === FALSE || $pos !== strlen($filename) - strlen($forbidden_extension)) {
        $output[] = $filename;
      }
    }
    // Ensure the templates DO NOT end in '.html.twig'.
    return $output;
  }

  /**
   * Finds assets related to the profided metadata file.
   *
   * @param string $component_directory
   *   The component directory for the plugin.
   * @param string $file_extension
   *   The file extension to detect.
   *
   * @return string[]
   *   Filenames relative to the cl_components module.
   */
  public function findAssets(string $component_directory, string $file_extension, bool $make_relative = FALSE): array {
    $cl_components_module_path = dirname(__FILE__, 4);
    // Use FilesystemIterator to not iterate over the . and .. directories.
    $flags = \FilesystemIterator::KEY_AS_PATHNAME
      | \FilesystemIterator::CURRENT_AS_FILEINFO
      | \FilesystemIterator::SKIP_DOTS;
    $directory_iterator = new \RecursiveDirectoryIterator($component_directory, $flags);
    $regex = sprintf('/^.+\\.%s$/i', preg_quote($file_extension, '/'));
    $filter = new RegexRecursiveFilterIterator($directory_iterator, $regex);
    $files = new \RecursiveIteratorIterator($filter, \RecursiveIteratorIterator::LEAVES_ONLY, $flags);
    $output = [];
    // Now find the JS and CSS files. We need to store them relative to
    // the cl_components module path. This is because we will use these paths in
    // a dynamic library definition that exists in the cl_components module.
    // Drupal will prepend the cl_components module path to the paths detected
    // here when adding the CSS and JS to the page.
    foreach ($files as $file_info) {
      assert($file_info instanceof \SplFileInfo);
      $absolute_path = $file_info->getPathname();
      $output[] = $make_relative
        ? $this->makePathRelative($absolute_path, $cl_components_module_path)
        : $absolute_path;
    }
    return $output;
  }

  /**
   * Takes two absolute paths and makes one relative to the other.
   *
   * @param string $full_path
   *   The full path.
   * @param string $base
   *   The base to make relative to.
   *
   * @return string
   *   The relative path.
   */
  private function makePathRelative(string $full_path, string $base): string {
    $app_root = \Drupal::root();
    $base_from_root = substr($base, strlen($app_root) + 1);
    $num_dots = count(explode(DIRECTORY_SEPARATOR, $base_from_root));
    $dots = implode(DIRECTORY_SEPARATOR, array_fill(0, $num_dots, '..'));
    $full_path_from_root = substr($full_path, strlen($app_root) + 1);
    return sprintf(
      '%s%s%s',
      $dots,
      DIRECTORY_SEPARATOR,
      $full_path_from_root,
    );
  }

}
