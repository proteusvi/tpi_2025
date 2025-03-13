<?php

namespace Drupal\cl_block\Plugin\CKEditorPlugin;

use Drupal\editor\Entity\Editor;
use Drupal\embed\EmbedCKEditorPluginBase;

/**
 * Defines the "clcomponent" plugin.
 *
 * @CKEditorPlugin(
 *   id = "clcomponent",
 *   label = @Translation("Render Element"),
 *   embed_type_id = "cl_block",
 *   required_filter_plugin_id = "cl_block",
 * )
 */
class ClComponentEmbed extends EmbedCKEditorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getFile() {
    return $this->getModulePath('cl_block') . '/js/plugins/clcomponents/plugin.js';
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(Editor $editor) {
    return [
      'ClComponent_dialogTitleAdd' => t('Insert CL Component'),
      'ClComponent_dialogTitleEdit' => t('Edit'),
      'ClComponent_buttons' => $this->getButtons(),
      'drupalEmbed_previewCsrfToken' => \Drupal::csrfToken()->get('X-Drupal-EmbedPreview-CSRF-Token'),
    ];
  }

}
