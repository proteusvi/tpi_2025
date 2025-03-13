<?php

/**
 * @file
 * Hooks provided by the CL Devel module.
 */

use Drupal\Core\Plugin\Component;

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Allow modules to alter the component card in the audit page.
 *
 * @param array $card_build
 *   The render array for the component card in the audit page.
 * @param \Drupal\Core\Plugin\Component $component
 *   The component object.
 */
function hook_cl_component_audit_alter(array &$card_build, Component $component): void {
}

/**
 * @} End of "addtogroup hooks".
 */
