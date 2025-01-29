<?php

namespace Drupal\tpi_entity_comparison\Service;

use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * A service that count how many items is in a list.
 */
class LazyCountItemsService implements TrustedCallbackInterface {

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

  public function __construct(
    Session $session,
    AccountInterface $current_user,
  ) {
    $this->session = $session;
    $this->currentUser = $current_user;
  }

  /**
   * Need a short doc.
   */
  public function renderComparisonCounter() {

    $count = $this->getNumberOfItems();

    // Si count est égal à 0 cela enlève la pastille.
    if ($count == 0) {
      $count = NULL;
    }

    $build = [
      '#theme' => 'tpi_entity_comparison_list_counter',
      '#itemsCount' => $count,
    ];

    return $build;
  }

  /**
   * Get number of the items.
   *
   * @return int
   *   Returns number of items.
   */
  public function getNumberOfItems() {
    $recipeCounter = $this->getListCount(LazyComparisonListService::LIST_COMPARISON_RECIPE_ID);
    $articleCounter = $this->getListCount(LazyComparisonListService::LIST_COMPARISON_ARTICLE_ID);

    $total = $recipeCounter + $articleCounter;

    if ($total == 0) {
      $total = NULL;
    }

    return $total;
  }

  /**
   * Get the counter for a list called by a user.
   *
   * @param string $comparisonListType
   *   The type of list name.
   *
   * @return int
   *   The counter.
   */
  public function getListCount(string $comparisonListType): int {
    // Get current entity comparison list.
    $entity_comparison_list = $this->session->get('entity_comparison_' . $this->currentUser->id());
    $count = 0;
    $list = [];

    switch ($comparisonListType) {
      case LazyComparisonListService::LIST_COMPARISON_RECIPE_ID:
        $baseList = !empty($entity_comparison_list['node']['recipe']) ? $entity_comparison_list['node']['recipe'] : [];
        if (array_key_exists(LazyComparisonListService::LIST_COMPARISON_RECIPE_ID, $baseList)) {
          $list = $baseList[$comparisonListType];
        }
        break;

      case LazyComparisonListService::LIST_COMPARISON_ARTICLE_ID:
        $baseList = !empty($entity_comparison_list['node']['article']) ? $entity_comparison_list['node']['article'] : [];
        if (array_key_exists(LazyComparisonListService::LIST_COMPARISON_ARTICLE_ID, $baseList)) {
          $list = $baseList[$comparisonListType];
        }
        break;
    }

    $count = count($list);

    return $count;
  }

  /**
   * Need a short doc.
   */
  public static function trustedCallbacks(): array {
    return [
      'renderComparisonCounter',
    ];
  }

}
