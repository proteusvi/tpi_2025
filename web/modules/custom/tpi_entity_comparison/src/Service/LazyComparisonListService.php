<?php

namespace Drupal\tpi_entity_comparison\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\entity_comparison\Entity\EntityComparisonInterface;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * A service that show how the list is.
 */
class LazyComparisonListService implements TrustedCallbackInterface {
  use StringTranslationTrait;

  const LIST_COMPARISON_RECIPE_ID = 'list_recipe_to_compare';
  const LIST_COMPARISON_ARTICLE_ID = 'list_article_to_compare';

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
   * The entity comparison storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $entityComparisonStorage;

  /**
   * The entity comparison storage.
   *
   * @var \Drupal\node\NodeStorageInterface
   */
  protected $nodeStorage;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    Session $session,
    EntityTypeManagerInterface $entityTypeManager,
    AccountInterface $current_user,
  ) {
    $this->session = $session;
    $this->entityComparisonStorage = $entityTypeManager->getStorage('entity_comparison');
    $this->nodeStorage = $entityTypeManager->getStorage('node');
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public function renderComparisonList() {
    // Get current user's id.
    $uid = $this->currentUser->id();

    /** @var \Drupal\entity_comparison\Entity\EntityComparisonInterface $entity_comparison */
    $entity_comparison = $this->entityComparisonStorage->load(self::LIST_COMPARISON_RECIPE_ID);
    $mmtsSessionList = $this->getList($entity_comparison, $uid);
    $entity_comparison = $this->entityComparisonStorage->load(self::LIST_COMPARISON_ARTICLE_ID);
    $stagesSessionList = $this->getList($entity_comparison, $uid);

    $mmtsList = [];
    $stagesList = [];
    if (!empty($mmtsSessionList)) {
      $mmtsList = $this->buildListToCompareFromNodes($mmtsSessionList[self::LIST_COMPARISON_RECIPE_ID]);
    }
    if (!empty($stagesSessionList)) {
      $stagesList = $this->buildListToCompareFromNodes($stagesSessionList[self::LIST_COMPARISON_RECIPE_ID]);
    }

    $build = [
      '#theme' => 'tpi_entity_comparison_list',
      '#title' => $this->t('Comparison lists'),
      '#list_mmt' => [
        'title' => $this->t('Recipe selected'),
        'list' => $mmtsList,
      ],
      '#list_stages' => [
        'title' => $this->t('Article selected'),
        'list' => $stagesList,
      ],
      '#attached' => [
        'library' => [
          'tpi_entity_comparison/tpi-compare-list-pop-up',
        ],
      ],
    ];

    return $build;
  }

  /**
   * Return list from User session from entity_comparison.
   *
   * @param \Drupal\entity_comparison\Entity\EntityComparisonInterface $entity_comparison
   *   The Entity comparison storage.
   * @param mixed $uid
   *   The user Id.
   *
   * @return array
   *   The list to compare for a type of content.
   */
  public function getList(EntityComparisonInterface $entity_comparison, mixed $uid): array {
    $list = [];

    // Get entity type and bundle type.
    $entityType = $entity_comparison->getTargetEntityType();
    $bundleType = $entity_comparison->getTargetBundleType();

    // Get current entity comparison list.
    $entity_comparison_list = $this->session->get('entity_comparison_' . $uid);
    if (isset($entity_comparison_list[$entityType][$bundleType])) {
      $list = $entity_comparison_list[$entityType][$bundleType];
    }

    return $list;
  }

  /**
   * Create list of title and prestataire name for comparison popup.
   *
   * @var array $nids
   *  Node ids list.
   *
   * @return array
   *   The list of Mmt's title and prestataires to compare.
   */
  protected function buildListToCompareFromNodes(array $nids) {
    if (empty($nids)) {
      return [];
    }
    $nodes = $this->nodeStorage->loadMultiple($nids);
    $compareList = [];

    /** @var \Drupal\node\NodeInterface $node */
    foreach ($nodes as $node) {
      $prestataireTitle = "";
      if ($node->get('field_prestataire')->getValue()[0]['target_id'] !== NULL) {
        $prestataire = $this->nodeStorage->load($node->get('field_prestataire')->getValue()[0]['target_id']);
        if ($prestataire !== NULL) {
          $prestataireTitle = $prestataire->getTitle();
        }
      }

      $compareList[] = [
        'title' => Link::createFromRoute(
            $node->getTitle(),
            'entity.node.canonical',
            ['node' => $node->id()]),
        'prestataire' => $prestataireTitle,
        'id' => $node->id(),
      ];
    }

    return $compareList;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return [
      'renderComparisonList',
    ];
  }

}
