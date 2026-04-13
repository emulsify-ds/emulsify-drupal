<?php

namespace Drupal\emulsify\Hook;

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
  public static function preprocessViewsView(array &$variables): void {
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
  public static function themeSuggestionsViewsViewAlter(array &$suggestions, array $variables): void {
    $view_id = $variables['view']->id();
    $display = $variables['view']->current_display;

    // Support global and per-display overrides.
    $suggestions[] = "views_view__{$view_id}";
    $suggestions[] = "views_view__{$view_id}__{$display}";
  }

  /**
   * Handles hook_theme_suggestions_views_view_unformatted_alter().
   *
   * @param array $suggestions
   *   Suggestions for the unformatted views style template.
   * @param array $variables
   *   Variables passed to the suggestion alter hook.
   */
  public static function themeSuggestionsViewsViewUnformattedAlter(array &$suggestions, array $variables): void {
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
  public static function themeSuggestionsViewsMiniPagerAlter(array &$suggestions, array $variables): void {
    // Keep a consistent mini pager override name across views.
    $suggestions[] = 'views_mini_pager';
  }

}
