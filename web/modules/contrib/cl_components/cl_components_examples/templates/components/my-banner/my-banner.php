<?php

/**
 * @file
 * Component PHP integration.
 */

use Drupal\cl_components\Plugin\Component;
use Drupal\Core\Form\FormStateInterface;

/**
 * Implements cl_component_COMPONENT_form_alter().
 */
function cl_component_my_banner_form_alter($form, FormStateInterface $form_state, Component $component): array {
  $form['data']['ctaTarget']['#options'] = [
    '' => \t('- No target -'),
    '_blank' => \t('Blank'),
  ];
  // @todo Use the media browser to select an image for the component.
  return $form;
}
