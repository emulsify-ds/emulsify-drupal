# Extend parent templates

Child themes can extend Emulsify's page, HTML, region, and base block templates
and override individual Twig blocks. Parent updates continue to reach the
sections the child inherits.

Drupal automatically registers the `@emulsify` Twig namespace. This is verified
on Drupal 11.3 and 11.4 with an active generated child theme; no namespace
configuration is needed. Use the explicit parent path when extending a
template with the same filename as the child's override:

```twig
{% extends "@emulsify/templates/layout/page.html.twig" %}
```

An unqualified `page.html.twig` does not explicitly select the parent and can
resolve back to the child's override. Use the namespaced reference consistently.

## Example: add content without copying the page wrapper

Newly generated child themes inherit the parent's page template without a local
copy. To customize it in a child theme named `my_theme`, create
`templates/layout/` and add `page.html.twig` containing:

```twig
{% extends "@emulsify/templates/layout/page.html.twig" %}

{% block page_content %}
  {{ parent() }}
  <p>{{ 'Thank you for visiting.'|t }}</p>
{% endblock %}
```

Rebuild Drupal's caches with `drush cr`. Drupal selects the child's page
override, then Twig loads the Emulsify parent explicitly. The child overrides
only `page_content`; the header, status messages, main wrapper, other regions,
and footer remain inherited. `parent()` keeps the original content region.
Omit that call only when intentionally replacing the entire selected section.

These are Twig template blocks, distinct from the Drupal blocks placed in
theme regions through the block layout UI.

## Supported extension points

Use the paths below after `@emulsify/templates/`.

| Template | Block | Default contents |
| --- | --- | --- |
| `layout/page.html.twig` | `page_header` | Header and status regions |
| `layout/page.html.twig` | `page_main` | Complete main element, skip-link target, and its regions |
| `layout/page.html.twig` | `page_content` | Content region, nested inside `page_main` |
| `layout/page.html.twig` | `page_footer` | Footer region |
| `layout/html.html.twig` | `head` | Head contents, including title and Drupal placeholders |
| `layout/html.html.twig` | `body_content` | Body contents, including the skip link, page, and bottom JavaScript placeholder |
| `layout/region.html.twig` | `region_content` | Region content inside its existing wrapper; rendered only when content exists |
| `block/block.html.twig` | `block_attributes` | Attributes on the opening block div |
| `block/block.html.twig` | `title` | Title prefix, conditional heading, and title suffix |
| `block/block.html.twig` | `content` | Existing block content extension point |

The names and boundaries above are a public template contract. Renaming a block
is a breaking change. Overriding `page_main` replaces its nested
`page_content` unless the override calls `parent()`. Keep Drupal's placeholders
when extending `head` or `body_content`, and preserve the skip link and its
target when changing page structure. Preserve `attributes` when extending
`block_attributes` so Drupal's IDs, classes, and data attributes survive.

## Verification

The readiness matrix compares all four parent templates against checked-in
HTML goldens captured before these extension points were added. It compares
exact bytes, including whitespace, with populated and empty content and with
visible and hidden block labels. It also temporarily gives a generated child a
page template that extends the parent and overrides only `page_content`, checks
Drupal's registry selection, and proves every surrounding byte is inherited.
The original child template is restored after the check.

Run that check in a disposable fixture after generating and enabling the child:

```bash
bash .github/scripts/setup-fixture-site.sh '11.4.*' /tmp/emulsify-fixture "$PWD"
bash .github/scripts/starterkit-smoke.sh /tmp/emulsify-fixture /tmp/emulsify-child generate
bash .github/scripts/starterkit-smoke.sh /tmp/emulsify-fixture /tmp/emulsify-child enable
script_path="$PWD/.github/scripts/template-extension-smoke.php"
(cd /tmp/emulsify-fixture && ./vendor/bin/drush php:script "$script_path")
```

Use `11.3.*` for the other blocking Drupal minor. The matrix runs the same
extension check on both versions.
