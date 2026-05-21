<?php

/**
 * @file
 * Theme hook wrappers for Drupal paragraph entities.
 */

use Drupal\emulsify\Hook\ParagraphHooks;

/**
 * Implements hook_preprocess_paragraph().
 */
function emulsify_preprocess_paragraph(array &$variables) {
  ParagraphHooks::preprocessParagraph($variables);
}
