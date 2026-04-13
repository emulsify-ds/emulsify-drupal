<?php

/**
 * @file
 * Theme hook wrappers for Drupal views.
 */

use Drupal\emulsify\Hook\ViewsHooks;

/**
 * Implements hook_preprocess_views_view().
 */
function emulsify_preprocess_views_view(array &$variables) {
  ViewsHooks::preprocessViewsView($variables);
}

/**
 * Implements hook_theme_suggestions_views_view_alter().
 */
function emulsify_theme_suggestions_views_view_alter(array &$suggestions, array $variables) {
  ViewsHooks::themeSuggestionsViewsViewAlter($suggestions, $variables);
}

/**
 * Implements hook_theme_suggestions_views_view_unformatted_alter().
 */
function emulsify_theme_suggestions_views_view_unformatted_alter(array &$suggestions, array $variables) {
  ViewsHooks::themeSuggestionsViewsViewUnformattedAlter($suggestions, $variables);
}

/**
 * Implements hook_theme_suggestions_views_mini_pager_alter().
 */
function emulsify_theme_suggestions_views_mini_pager_alter(array &$suggestions, array $variables) {
  ViewsHooks::themeSuggestionsViewsMiniPagerAlter($suggestions, $variables);
}
