<?php

/**
 * @file
 */

namespace Drupal\cl_components;

enum ExtensionType: string {
  case Module = 'module';
  case Theme = 'theme';
}
