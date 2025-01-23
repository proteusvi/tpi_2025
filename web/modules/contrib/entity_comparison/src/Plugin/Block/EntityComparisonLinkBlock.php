<?php

namespace Drupal\entity_comparison\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Utility\LinkGenerator;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Provides a generic entity comparison link block.
 *
 * @Block(
 *   id = "entity_comparison_link_block",
 *   admin_label = @Translation("Comparison"),
 *   category = @Translation("Comparisons"),
 *   deriver = "Drupal\entity_comparison\Plugin\Derivative\EntityComparisonLinkBlock"
 * )
 */
class EntityComparisonLinkBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entity comparison storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $entityComparison;

  /**
   * Link generator.
   *
   * @var \Drupal\Core\Utility\LinkGenerator
   */
  protected $linkGenerator;

  /**
   * The current user session.
   *
   * @var \Symfony\Component\HttpFoundation\Session\Session
   */
  protected $session;

  /**
   * The current user object.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs new EntityComparisonLinkBlock.
   *
   * @param array $configuration
   *   Configuration array.
   * @param string $plugin_id
   *   Plugin ID.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $entity_comparison
   *   The entity comparison storage.
   * @param \Drupal\Core\Utility\LinkGenerator $link_generator
   *   Link generator.
   * @param \Symfony\Component\HttpFoundation\Session\Session $session
   *   The current user session.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityStorageInterface $entity_comparison, LinkGenerator $link_generator, Session $session, AccountProxyInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityComparison = $entity_comparison;
    $this->linkGenerator = $link_generator;
    $this->session = $session;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getStorage('entity_comparison'),
      $container->get('link_generator'),
      $container->get('session'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $config = $this->configuration;

    $defaults = $this->defaultConfiguration();

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Load the related entity comparison.
    $entity_comparison_id = $this->getDerivativeId();
    $entity_comparison = $this->entityComparison->load($entity_comparison_id);
    // Get current entity ID.
    $node = \Drupal::routeMatch()->getParameter('node');
    if ($node instanceof NodeInterface) {
      // You can get nid and anything else you need from the node object.
      return [
        '#theme' => 'entity_comparison_link',
        '#id' => $node->id(),
        '#entity_comparison' => $entity_comparison_id,
        '#cache' => [
          'max-age' => 0,
        ],
        '#access' => \Drupal::currentUser()->hasPermission("use {$entity_comparison_id} entity comparison"),
      ];
    }

    return [
      '#markup' => $entity_comparison->getAddLinkText(),
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    // Even when the entity comparison block renders to the empty string
    // for a user, we want the cache tag for this menu to be set:
    // whenever the comparison list is changed, this entity comparison block
    // must also be re-rendered for that user.
    $cache_tags = parent::getCacheTags();
    $cache_tags[] = 'config:entity_comparison.' . $this->getDerivativeId();
    return $cache_tags;
  }

}
