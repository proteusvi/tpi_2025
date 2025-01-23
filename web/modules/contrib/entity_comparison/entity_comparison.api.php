<?php

/**
 * @file
 * Describes hooks and plugins provided by the Entity Comparison module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter the Entity Comparison table rows before it is rendered.
 *
 * @param array $header
 *   The header of the table.
 * @param array $rows
 *   The rows of the table.
 * @param array $comparison_context
 *   The comparison context. Contains:
 *   The entity comparison object - 'entity_comparison',
 *   Entities to compare - 'entities',
 *   Fields to compare - 'comparison_fields'.
 */
function hook_entity_comparison_rows_alter(array &$header, array &$rows, array $comparison_context) {
  // Set header.
  $extra_row = [t('Header')];

  $entities = $comparison_context['entities'];
  foreach ($entities as $entity) {
    // Place your text here.
    $extra_row[] = t('Field');
  }

  // Add new row.
  $rows[] = $extra_row;
}

/**
 * @} End of "addtogroup hooks".
 */
