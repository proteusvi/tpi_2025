<?php

namespace Drupal\tpi_entity_comparison\Service;

use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * A service that show how the list is.
 */
class LazyComparisonButtonService implements TrustedCallbackInterface {

  /**
   * The current user object.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The service comparison manage session list.
   *
   * @var LazyComparisonListService
   */
  protected $comparisonManageSessionList;

  /**
   * The session manager service.
   *
   * @var ManageSessionList
   */
  protected $sessionManager;

  public function __construct(
    LazyComparisonListService $comparisonManageSessionList,
    ManageSessionList $sessionManager,
    AccountInterface $current_user,
  ) {
    $this->comparisonManageSessionList = $comparisonManageSessionList;
    $this->sessionManager = $sessionManager;
    $this->currentUser = $current_user;
  }

  /**
   * Render the link (button) block with the icon.
   *
   * @param string $entity_id
   *   The entity id (nid).
   * @param string $entity_comparison_id
   *   The comparison list name.
   *
   * @return array
   *   The block render.
   */
  public function renderComparisonButton(string $entity_id, string $entity_comparison_id) {
    // Generate the link render array.
    $isDisabled = $this->sessionManager
      ->isEntityInCompareList($entity_id);
    $pathCompareList = $entity_comparison_id;
    $compareListId = str_replace('_', '-', $entity_comparison_id);

    $build = [
      '#theme' => 'tpi_entity_comparison_action_btn',
      '#itemId' => $entity_id,
      '#compareListId' => $compareListId,
      '#pathCompareList' => $pathCompareList,
      '#isDisabled' => $isDisabled,
      '#cache' => [
        'max-age' => 0,
      ],
      '#access' => $this->currentUser->hasPermission("tpi comparison list access"),
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return [
      'renderComparisonButton',
    ];
  }

}
