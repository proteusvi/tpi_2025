<?php

namespace Drupal\cl_block\Plugin\EmbedType;

use Drupal\Core\Form\FormStateInterface;
use Drupal\embed\EmbedType\EmbedTypeBase;

/**
 * Principal Design System Element embed type.
 *
 * @EmbedType(
 *   id = "cl_block",
 *   label = @Translation("CL Component"),
 * )
 */
class ClComponentEmbed extends EmbedTypeBase {

  /**
   * {@inheritdoc}
   */
  public function getDefaultIconUrl() {
    $resolver = \Drupal::service('extension.path.resolver');
    $url_generator = \Drupal::service('file_url_generator');
    $path = $resolver->getPath('module', 'cl_block') . '/js/plugins/clcomponents/component.svg';
    return $url_generator->generateAbsoluteString($path);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['component_id' => ''];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['component_id'] = [
      '#type' => 'cl_component_selector',
      '#title' => $this->t('CL Component'),
      '#description' => $this->t('Leave it <strong>empty</strong> to let the editor select during the embedding process.'),
      '#default_value' => $this->configuration['component_id'],
    ];

    return $form;
  }

}
