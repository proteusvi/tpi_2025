<?php

namespace Drupal\cl_block\Plugin\Derivative;

use Drupal\cl_components\Plugin\Component;
use Drupal\cl_editorial\NoThemeComponentManager;
use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides component block definitions for every field.
 *
 * @internal
 *   Plugin derivers are internal.
 */
class ComponentBlockDeriver extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function __construct(protected readonly NoThemeComponentManager $componentManager, protected readonly ConfigFactoryInterface $configFactory) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    $service_ids = [NoThemeComponentManager::class, 'config.factory'];
    return new static(...array_map([$container, 'get'], $service_ids));
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $settings = $this->configFactory->get('cl_block.settings');
    $components = $this->componentManager->getFilteredComponentTypes(
      $settings->get('allowed'),
      $settings->get('forbidden'),
      $settings->get('types'),
      $settings->get('statuses'),
    );
    $this->derivatives = \array_reduce(
      $components,
      function (array $carry, Component $component) use ($base_plugin_definition) {
        $carry[$component->getId()] = array_merge($base_plugin_definition, [
          'admin_label' => $component->getMetadata()->getName(),
          'category' => $this->t('CL Components'),
        ]);
        return $carry;
      },
      []
    );
    return $this->derivatives;
  }

}
