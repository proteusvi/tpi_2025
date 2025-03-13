<?php

namespace Drupal\cl_devel\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Plugin\Component;
use Drupal\Core\Render\Component\Exception\InvalidComponentException;
use Drupal\Core\Theme\ComponentPluginManager;
use Drupal\Core\Theme\ExtensionType;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for the registry.
 */
final class ComponentAudit extends ControllerBase {

  /**
   * Creates a ComponentAudit object.
   *
   * @param \Drupal\Core\Theme\ComponentPluginManager $pluginManager
   *   The plugin manager.
   */
  public function __construct(private readonly ComponentPluginManager $pluginManager) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $plugin_manager = $container->get('plugin.manager.sdc');
    assert($plugin_manager instanceof ComponentPluginManager);
    return new static($plugin_manager);
  }

  /**
   * Render the registry.
   *
   * @return array
   *   The render array.
   *
   * @throws \Drupal\Core\Render\Component\Exception\ComponentNotFoundException
   */
  public function audit(): array {
    $components = $this->pluginManager->getAllComponents();
    // We can only know for certain what components are forked if they are in
    // a module.
    $module_components = array_filter(
      $components,
      static fn(Component $component) => ($component->getPluginDefinition()['extension_type'] ?? '') === ExtensionType::Module
    );
    $duped_components = array_filter(
      $module_components,
      fn(Component $component) => ($component->getPluginId()) !== ($this->pluginManager->find($component->getPluginId())
        ->getPluginId())
    );
    $build_component_card = fn(Component $component) => $this->buildComponentCard($component, $duped_components);
    usort($components, static fn(Component $a, Component $b) => $a->getPluginId() <=> $b->getPluginId());
    return [
      'logs' => [
        '#prefix' => '<p>',
        '#markup' => $this->t('Remember to check your logs if you are having problems with your components.'),
        '#suffix' => '</p>',
      ],
      'components' => [
        '#type' => 'container',
        '#attributes' => ['class' => 'panel__container'],
        '#attached' => ['library' => ['cl_devel/cl_registry']],
        'panels' => [
          '#theme' => 'item_list',
          '#title' => $this->t('Detected Components'),
          '#items' => array_map($build_component_card, $components),
        ],
        'recommendations' => [
          '#prefix' => '<p>',
          '#markup' => $this->t('We recommend you to add a <code>README.md</code> file and a <code>thumbnail.png</code> to all your components. Other modules and JS tools may use them to provide additional insight.
'),
          '#suffix' => '</p>',
        ],
      ],
    ];
  }

  /**
   * Builds the render array for the component card.
   *
   * @param \Drupal\Core\Plugin\Component $component
   *   The current component.
   * @param \Drupal\Core\Plugin\Component[] $duped_components
   *   The components without duplications (for forked components).
   *
   * @return array[]
   *   The card render array.
   */
  private function buildComponentCard(Component $component, array $duped_components): array {
    $metadata = $component->metadata;
    $path = $metadata->path;
    try {
      $matches = array_filter($duped_components, static fn(Component $cmp) => $cmp->getPluginId() === $component->getPluginId());
      $match = reset($matches);
      $is_forked = $match && $match->metadata->path === $path;
    }
    catch (InvalidComponentException $e) {
      return [
        'title' => [
          '#prefix' => '<h4>',
          '#markup' => $this->t('💥 Error in Component'),
          '#suffix' => '</h4>',
        ],
        'path' => [
          '#prefix' => '<pre>',
          '#markup' => $path,
          '#suffix' => '</pre>',
        ],
        'error' => [
          '#prefix' => '<p>',
          '#markup' => $e->getMessage(),
          '#suffix' => '</p>',
        ],
        '#wrapper_attributes' => ['class' => 'panel-item'],
      ];
    }
    $default_template = $component->machineName . '.twig';
    $template_message = $default_template === $component->template
      ? $this->t('✅ %default template is present', ['%default' => $default_template])
      : $this->t('❌ @id.twig is missing', ['@id' => $component->machineName]);
    $assets = $component->getPluginDefinition()['library'] ?? [];
    $assets_message = [
      '#theme' => 'item_list',
      '#items' => array_map(
        static fn($file) => [
          '#type' => 'html_tag',
          '#tag' => 'code',
          '#value' => preg_replace('@.*/' . $component->machineName . '/@', '', $file),
        ],
        [
          ...array_keys($assets['js'] ?? []),
          ...array_keys($assets['css']['component'] ?? []),
        ]
      ),
    ];
    if (!$is_forked) {
      $forked_message = [
        '#type' => 'html_tag',
        '#tag' => 'em',
        '#value' => $this->t('Original component'),
      ];
    }
    else {
      $forked_message = [
        '#type' => 'html_tag',
        '#tag' => 'em',
        '#value' => $this->t('Forked at <code>@filename</code>.', [
          '@filename' => $this->pluginManager->find($component->getPluginId())
            ->metadata
            ->path,
        ]),
      ];
    }
    $card_build = [
      'title' => [
        '#theme' => 'cl_label_with_link',
        '#label' => Link::createFromRoute($metadata->name . ($is_forked ? ' ❗' : ''), 'cl_devel.component_details', ['component_id' => $component->getPluginId()]),
        '#href' => '#' . $component->getPluginId(),
      ],
      'description' => $metadata
        ->description === $this->t('- Description not available -') ? [] : [
          '#prefix' => '<p>',
          '#markup' => $metadata->description,
          '#suffix' => '</p>',
        ],
      'path' => [
        '#prefix' => '<pre>',
        '#markup' => $path,
        '#suffix' => '</pre>',
      ],
      'forked' => $is_forked ? [
        '#markup' => $this->t('When rendering this component, this other component will render instead: <code>@filename</code>.', [
          '@filename' => $this->pluginManager->find($component->getPluginId())
            ->metadata
            ->path,
        ]),
      ] : [],
      'table' => [
        '#theme' => 'table',
        '#header' => [
          $this->t('Metadata'),
          $this->t('Default Template'),
          $this->t('Assets'),
          $this->t('Component Replacement'),
        ],
        '#rows' => [
          [
            $this->t('✅ All metadata is correct'),
            $template_message,
            ['data' => $assets_message],
            ['data' => $forked_message],
          ],
        ],
      ],
      '#wrapper_attributes' => [
        'class' => ['panel-item', $is_forked ? 'forked' : 'not-forked'],
        'id' => $component->getPluginId(),
      ],
    ];
    if (!$is_forked) {
      $this->moduleHandler()
        ->alter('cl_component_audit', $card_build, $component);
    }
    return $card_build;
  }

}
