<?php

declare(strict_types=1);

namespace Drupal\emulsify\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;

/**
 * Theme hook handlers for menu templates.
 */
final class MenuHooks {

  public function __construct(
    private readonly RouteMatchInterface $routeMatch,
    private readonly PathMatcherInterface $pathMatcher,
  ) {}

  /**
   * Handles hook_preprocess_menu().
   *
   * @param array $variables
   *   Variables passed to the menu template.
   */
  #[Hook('preprocess_menu')]
  public function preprocessMenu(array &$variables): void {
    // Active-trail cache contexts alone can cover several different pages.
    $variables['#cache']['contexts'][] = 'route';
    $variables['#cache']['contexts'][] = 'url.path.is_front';
    $matched = FALSE;
    $this->markCurrentLink($variables['items'], $matched);
  }

  /**
   * Marks the first matching link, leaving ancestors and duplicates unmarked.
   *
   * @param array $items
   *   Menu items, including their nested children.
   * @param bool $matched
   *   Whether a current-page link has already been marked in this menu.
   */
  private function markCurrentLink(array &$items, bool &$matched): void {
    foreach ($items as &$item) {
      $item['current_link_attributes'] = [];
      if (!$matched && $item['url'] instanceof Url && $this->isCurrentUrl($item['url'])) {
        $item['current_link_attributes']['aria-current'] = 'page';
        $matched = TRUE;
      }
      if (!empty($item['below'])) {
        $this->markCurrentLink($item['below'], $matched);
      }
    }
  }

  /**
   * Compares a link's route and parameters with the current page.
   *
   * @param \Drupal\Core\Url $url
   *   The menu link destination.
   */
  private function isCurrentUrl(Url $url): bool {
    if (!$url->isRouted() || in_array($url->getRouteName(), ['<nolink>', '<button>'], TRUE)) {
      return FALSE;
    }
    if ($url->getRouteName() === '<front>') {
      return $this->pathMatcher->isFrontPage();
    }
    if ($url->getRouteName() !== $this->routeMatch->getRouteName()) {
      return FALSE;
    }

    // Compare raw IDs, not the upcast entities in getParameters(). Normalize
    // integer IDs to strings while keeping distinct strings like "01" and "1".
    $parameters = array_map('strval', $url->getRouteParameters());
    $current_parameters = array_map('strval', $this->routeMatch->getRawParameters()->all());
    ksort($parameters);
    ksort($current_parameters);
    return $parameters === $current_parameters;
  }

}
