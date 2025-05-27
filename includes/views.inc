<?php

/**
 * @file
 * Theme hook implementations for Drupal Views.
 */

/**
 * Implements hook_preprocess_views_view().
 *
 * Prepares variables for all Views templates:
 * - Adds the current request path to $variables['path'].
 * - If no title is set in the template but the View provides one,
 *   renders it as a '#markup' render array.
 */
function emulsify_preprocess_views_view(array &$variables) {
  /** @var ViewExecutable $view */
  $view = $variables['view'];

  // Expose the current URL path (e.g., '/articles').
  $variables['path'] = $view->getRequest()->getPathInfo();

  // If the template has no title but the View has one, set it.
  if (empty($variables['title']) && $title = $view->getTitle()) {
    $variables['title'] = [
      '#markup' => $title,
    ];
  }
}

/**
 * Implements hook_theme_suggestions_views_view_alter().
 *
 * Adds template suggestions for the full View wrapper:
 * - views-view--[view_id].html.twig.
 * - views-view--[view_id]--[display_id].html.twig.
 */
function emulsify_theme_suggestions_views_view_alter(array &$suggestions, array $variables) {
  $view_id = $variables['view']->id();
  $display = $variables['view']->current_display;

  // Add base and display-specific suggestions.
  $suggestions[] = "views_view__{$view_id}";
  $suggestions[] = "views_view__{$view_id}__{$display}";
}

/**
 * Implements hook_theme_suggestions_views_view_unformatted_alter().
 *
 * Adds template suggestions for the unformatted list style:
 * - views-view-unformatted--[view_id].html.twig.
 * - views-view-unformatted--[view_id]--[display_id].html.twig.
 */
function emulsify_theme_suggestions_views_view_unformatted_alter(array &$suggestions, array $variables) {
  $view_id = $variables['view']->id();
  $display = $variables['view']->current_display;

  $suggestions[] = "views_view_unformatted__{$view_id}";
  $suggestions[] = "views_view_unformatted__{$view_id}__{$display}";
}

/**
 * Implements hook_theme_suggestions_views_mini_pager_alter().
 *
 * Adds a generic template suggestion for the mini pager, allowing
 * custom theming of the pager across all Views:
 * - views-mini-pager.html.twig.
 */
function emulsify_theme_suggestions_views_mini_pager_alter(array &$suggestions, array $variables) {
  $suggestions[] = 'views_mini_pager';
}
