<?php

namespace Drupal\entity_comparison\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_comparison\Entity\EntityComparisonInterface;

/**
 * Plugin implementation of the 'entity_comparison_link' formatter.
 *
 * @FieldFormatter(
 *   id = "entity_comparison_link",
 *   label = @Translation("Entity comparison link"),
 *   field_types = {
 *     "entity_comparison_link"
 *   }
 * )
 */
class EntityComparisonLinkFieldFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'enitity_comparison' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $options = array_map(static function (EntityComparisonInterface $entity) {
      return $entity->label();
    }, \Drupal::entityTypeManager()->getStorage('entity_comparison')->loadMultiple());
    return [
      'enitity_comparison' => [
        '#type' => 'select',
        '#options' => $options,
        '#default_value' => $this->getSetting('enitity_comparison'),
      ],
    ] + parent::settingsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    // Implement settings summary.
    $enitity_comparison = $this->getSetting('enitity_comparison');

    $summary = [];
    $summary[] = $this->t('Entity comparison: @enitity_comparison', ['@enitity_comparison' => $enitity_comparison]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $account = \Drupal::currentUser();
    $elements = [];

    $elements[] = [
      '#theme' => 'entity_comparison_link',
      '#id' => $items->getEntity()->id(),
      '#entity_comparison' => $this->getSetting('enitity_comparison'),
      '#cache' => [
        'max-age' => 0,
      ],
      '#access' => $account->hasPermission("use " . $this->getSetting('enitity_comparison') . " entity comparison"),
    ];

    return $elements;
  }

}
