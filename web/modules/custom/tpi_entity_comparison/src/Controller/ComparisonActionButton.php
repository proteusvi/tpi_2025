<?php

namespace Drupal\tpi_entity_comparison\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InsertCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\entity_comparison\Controller\EntityComparisonController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller to manage adding/removing entity in comparison list.
 *
 * Do the action and refresh DOM via AJAX command.
 */
class ComparisonActionButton extends EntityComparisonController {

  /**
   * Action buttons Add and remove to/from comparison list.
   *
   * @param string $entity_comparison_id
   *   The comparison list name.
   * @param string $entity_id
   *   The entity id (nid).
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request from the view.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse|RedirectResponse
   *   The response from action.
   */
  public function action($entity_comparison_id, $entity_id, Request $request): AjaxResponse|RedirectResponse {
    // 1. Add the item to the list.
    $response = parent::action($entity_comparison_id, $entity_id, $request);
    if ($response instanceof AjaxResponse) {
      $counterOfItems = \Drupal::service('tpi_entity_comparison.lazy_counter_items_service')
        ->getListCount($entity_comparison_id);
      if ($counterOfItems <= 3) {
        $refreshComparisonButton = \Drupal::service('tpi_entity_comparison.lazy_comparison_button_service')
          ->renderComparisonButton($entity_id, $entity_comparison_id);
        $compareListId = str_replace('_', '-', $entity_comparison_id);
        // Generate a CSS selector to use in a JQuery Replace command.
        $selector = '[data-entity-comparison=' . $compareListId . '-' . $entity_id . ']';
        // Create a new JQuery Replace command to update the link display.
        $replace = new ReplaceCommand($selector, $this->renderer->renderInIsolation($refreshComparisonButton));
        $response->addCommand($replace);
      }

      // Refresh the badge counter.
      $badgeCounter = $this->refreshItemsCount();
      $selector = '[data-entity-comparison-counter]';
      $replaceBadgeCounter = new ReplaceCommand($selector, $this->renderer->renderInIsolation($badgeCounter));
      $response->addCommand($replaceBadgeCounter);

      $refreshList = \Drupal::service('tpi_entity_comparison.lazy_comparison_list_service')
        ->renderComparisonList();
      $response->addCommand(new InsertCommand('#lazy-list-comparison', $refreshList));

      foreach ($response->getCommands() as &$commandItem) {
        if ($commandItem['command'] == "message") {
          $commandItem['messageWrapperQuerySelector'] = "#toast-wrapper .toast-body";
        }
      }
      // Open the toast to show messages.
      $selector = '.toast';
      $method = 'toast';
      $arguments = ['show'];
      $response->addCommand(new InvokeCommand($selector, $method, $arguments));
    }
    return $response;
  }

  /**
   * Return the new counter to render.
   *
   * @return array
   *   Array to render.
   */
  protected function refreshItemsCount(): array {
    // Generate the link render array.
    $count = \Drupal::service('tpi_entity_comparison.manage_session_list')
      ->getNumberOfItems();
    return [
      '#theme' => 'tpi_entity_comparison_list_counter',
      '#itemsCount' => $count,
      '#cache' => [
        'max-age' => 0,
      ],
      '#access' => $this->currentUser->hasPermission("tpi comparison list access"),
    ];
  }

}
