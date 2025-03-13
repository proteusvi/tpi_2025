<?php

/**
 * @file
 * Component PHP integration.
 */

use Drupal\cl_components\Plugin\Component;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;

/**
 * Implements cl_component_COMPONENT_library_info_alter().
 */
function cl_component_my_button_library_info_alter($library, Component $component): array {
  // Ensure all JS for the button is deferred.
  $library['js'] = array_map(
    static fn(array $value) => NestedArray::mergeDeep($value, ['attributes' => ['defer' => TRUE]]),
    $library['js'] ?? []
  );
  return $library;
}

/**
 * Implements cl_component_COMPONENT_form_alter().
 */
function cl_component_my_button_form_alter($form, FormStateInterface $form_state, Component $component): array {
  $form['data']['iconType']['#description'] = \t('The component type will determine the icon in the button.');
  $form['data']['iconType']['#default_value'] = $form['data']['iconType']['#default_value'] ?? 'power';
  $form['data']['text']['#placeholder'] = \t('Click me!');
  return $form;
}
