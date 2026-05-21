<?php

declare(strict_types=1);

namespace Drupal\emulsify\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Theme hook handlers for Views templates.
 */
final class ViewsHooks {

  /**
   * Handles hook_preprocess_views_view().
   *
   * @param array $variables
   *   Variables passed to Views templates.
   */
  #[Hook('preprocess_views_view')]
  public function preprocessViewsView(array &$variables): void {
    $view = $variables['view'];

    // Provide the current request path to simplify path-aware templates.
    $variables['path'] = $view->getRequest()->getPathInfo();

    if (empty($variables['title']) && ($title = $view->getTitle())) {
      // Views can return a plain title string; wrap as render markup.
      $variables['title'] = [
        '#markup' => $title,
      ];
    }
  }

  /**
   * Handles hook_theme_suggestions_views_view_alter().
   *
   * @param array $suggestions
   *   Suggestions for the main views-view wrapper template.
   * @param array $variables
   *   Variables passed to the suggestion alter hook.
   */
  #[Hook('theme_suggestions_views_view_alter')]
  public function themeSuggestionsViewsViewAlter(array &$suggestions, array $variables): void {
    $view = $variables['view'] ?? NULL;

    if (!is_object($view) || !method_exists($view, 'id')) {
      return;
    }

    $view_id = str_replace('-', '_', (string) $view->id());
    $display = str_replace('-', '_', (string) ($view->current_display ?? ''));
    $display_type = NULL;

    if (isset($view->display_handler) && is_object($view->display_handler)) {
      $display_handler = $view->display_handler;

      if (method_exists($display_handler, 'getPluginId')) {
        $display_type = $display_handler->getPluginId();
      }
      elseif (isset($display_handler->display) && is_array($display_handler->display)) {
        $display_type = $display_handler->display['display_plugin'] ?? NULL;
      }
    }

    $display_type = $display_type ? str_replace('-', '_', (string) $display_type) : '';

    // Support global, display-type, and specific display overrides.
    if ($view_id) {
      $this->addSuggestion($suggestions, "views_view__{$view_id}");
    }

    if ($view_id && $display_type) {
      $this->addSuggestion($suggestions, "views_view__{$view_id}__{$display_type}");
    }

    if ($view_id && $display) {
      $this->addSuggestion($suggestions, "views_view__{$view_id}__{$display}");
    }
  }

  /**
   * Handles hook_theme_suggestions_views_view_unformatted_alter().
   *
   * @param array $suggestions
   *   Suggestions for the unformatted views style template.
   * @param array $variables
   *   Variables passed to the suggestion alter hook.
   */
  #[Hook('theme_suggestions_views_view_unformatted_alter')]
  public function themeSuggestionsViewsViewUnformattedAlter(array &$suggestions, array $variables): void {
    $view_id = $variables['view']->id();
    $display = $variables['view']->current_display;

    // Mirror the same granularity for the unformatted style plugin.
    $suggestions[] = "views_view_unformatted__{$view_id}";
    $suggestions[] = "views_view_unformatted__{$view_id}__{$display}";
  }

  /**
   * Handles hook_theme_suggestions_views_mini_pager_alter().
   *
   * @param array $suggestions
   *   Suggestions for the mini pager template.
   * @param array $variables
   *   Variables passed to the suggestion alter hook.
   */
  #[Hook('theme_suggestions_views_mini_pager_alter')]
  public function themeSuggestionsViewsMiniPagerAlter(array &$suggestions, array $variables): void {
    $view = $variables['view'] ?? NULL;

    if (!is_object($view) || !method_exists($view, 'id') || empty($view->current_display)) {
      return;
    }

    $view_id = str_replace('-', '_', (string) $view->id());
    $display = str_replace('-', '_', (string) $view->current_display);

    // Add useful per-view pager overrides; the base hook is already available.
    $suggestions[] = "views_mini_pager__{$view_id}";
    $suggestions[] = "views_mini_pager__{$view_id}__{$display}";
  }

  /**
   * Adds a suggestion if it is not already present.
   *
   * @param array $suggestions
   *   Theme hook suggestions.
   * @param string $suggestion
   *   Suggestion to add.
   */
  private function addSuggestion(array &$suggestions, string $suggestion): void {
    if (!in_array($suggestion, $suggestions, TRUE)) {
      $suggestions[] = $suggestion;
    }
  }

}
