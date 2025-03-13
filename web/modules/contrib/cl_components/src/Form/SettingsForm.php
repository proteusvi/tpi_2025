<?php

namespace Drupal\cl_components\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Storybook Components settings for this site.
 */
final class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cl_components_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['cl_components.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    if (!\Drupal::moduleHandler()->moduleExists('cl_devel')) {
      $this->messenger()->addWarning($this->t(
        'CL Devel is not installed. The <a href="https://www.drupal.org/project/cl_devel" target="_blank" rel="nofollow noopener">CL Devel module</a> provides useful insights for your components, and it is safe to use in any of your environments, including production.'
      ));
    }
    $debug = $this->config('cl_components.settings')->get('debug');
    $form['debug'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Debug'),
      '#description' => $this->t('Use additional debugging tools for components. This is in addition to the debug HTML comments added to the DOM when setting <code>twig.config.debug: true</code> in your development.services.yml container.'),
      '#default_value' => $debug,
    ];
    $form = parent::buildForm($form, $form_state);
    $form['actions']['clear'] = [
      '#type' => 'submit',
      '#value' => $this->t('Clear Discovery Cache'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Check if the configuration form was saved or the cache clear triggered.
    $element = $form_state->getTriggeringElement();
    if (end($element['#array_parents']) === 'clear') {
      $cache = \Drupal::cache('component_registry');
      $cache->deleteAll();
      $this->messenger()->addMessage($this->t('The component discovery cache has been cleared.'));
      return;
    }
    $debug = (bool) $form_state->getValue('debug');
    $this->config('cl_components.settings')
      ->set('debug', $debug)
      ->save();
    parent::submitForm($form, $form_state);
  }

}
