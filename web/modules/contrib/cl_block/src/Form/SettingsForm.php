<?php

namespace Drupal\cl_block\Form;

use Drupal\cl_editorial\Form\ComponentFiltersFormTrait;
use Drupal\cl_editorial\NoThemeComponentManager;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Component Libraries: Blocks settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  use ComponentFiltersFormTrait;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    private readonly NoThemeComponentManager $componentManager,
    private readonly BlockManagerInterface $blockManager
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $component_manager = $container->get(NoThemeComponentManager::class);
    return new static(
      $container->get('config.factory'),
      $component_manager,
      $container->get('plugin.manager.block')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cl_block_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['cl_block.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $settings = $this->config('cl_block.settings')->get();
    // I am unsure why we need this, but the component discovery is not
    // initialized in ajax callbacks sometimes.
    $component_manager = $this->componentManager ?? \Drupal::service(NoThemeComponentManager::class);
    $message = $this->t('Set a combination of filters to control the list of components that will become blocks.');
    static::buildSettingsForm($form, $form_state, $component_manager, $settings, [], $message);
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    static::validates($form, $form_state);
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $types = array_keys(array_filter($form_state->getValue(['filters', 'types'])));
    $statuses = array_keys(array_filter($form_state->getValue(['filters', 'statuses'])));
    $forbidden = array_keys(array_filter($form_state->getValue(['filters', 'refine', 'forbidden'])));
    $allowed = array_keys(array_filter($form_state->getValue(['filters', 'refine', 'allowed'])));
    $this->config('cl_block.settings')
      ->set('types', $types)
      ->set('statuses', $statuses)
      ->set('forbidden', $forbidden)
      ->set('allowed', $allowed)
      ->save();
    // Refresh the list of blocks after saving settings.
    $manager = isset($this->blockManager) ? $this->blockManager : \Drupal::service('plugin.manager.block');
    $manager->clearCachedDefinitions();
    parent::submitForm($form, $form_state);
  }

}
