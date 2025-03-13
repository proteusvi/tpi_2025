<?php

namespace Drupal\cl_components\Plugin\Discovery;

/**
 * Iterates over files whose names match a regular expression in a directory.
 */
class RegexRecursiveFilterIterator extends \RecursiveFilterIterator {

  /**
   * The regular expression to match.
   *
   * @var string
   */
  protected string $regex;

  /**
   * RegexDirectoryIterator constructor.
   *
   * @param \RecursiveIterator $iterator
   *   The iterator.
   * @param string $regex
   *   The regular expression to match, including delimiters. For example,
   *   /\.yml$/ would list only files ending in .yml.
   */
  public function __construct(\RecursiveIterator $iterator, string $regex = '') {
    parent::__construct($iterator);
    $this->regex = $regex;
  }

  /**
   * {@inheritdoc}
   */
  #[\ReturnTypeWillChange]
  public function accept() {
    /** @var \SplFileInfo $file_info */
    $file_info = $this->getInnerIterator()->current();
    if ($file_info->isDir()) {
      // Enter into subdirectory.
      return TRUE;
    }
    // Return if file matches regular expression.
    return $file_info->isFile() && preg_match($this->regex, $file_info->getFilename());
  }

  /**
   * {@inheritdoc}
   */
  #[\ReturnTypeWillChange]
  public function getChildren() {
    $children = parent::getChildren();
    if ($children instanceof self && empty($children->regex)) {
      $children->regex = $this->regex;
    }
    return $children;
  }

}
