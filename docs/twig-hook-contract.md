# Twig hook output contract

This document records current Emulsify Drupal hook output. It characterizes
existing behavior, including inconsistent normalization and empty-value handling;
it does not propose convergence. Generation and copied starter files have a
separate [generated child-theme contract](./generated-child-theme-contract.md).

## Paragraph and field variables

`ParagraphHooks::preprocessParagraph()` reads `paragraph`, its immediate parent,
`elements`, and `attributes` from the Drupal render variables:

| Variable | Current output |
| --- | --- |
| `paragraph_index` | Always assigned from `elements['#emulsify_paragraph_index']`, or `NULL` when absent/null. No coercion: `0` stays an integer and `'03'` stays a string. |
| `parent_type` | Immediate parent's `getEntityTypeId()` result, assigned only when a parent exists. |
| `parent_bundle` | Immediate parent's `bundle()` result, with spelling/punctuation unchanged. |
| `node_title` | Immediate parent's raw `label()` result only when that parent implements `NodeInterface`; no escaping or ancestor traversal. Nested paragraphs do not discover an enclosing node title. |
| `container__attributes` | Copy of a nonempty PHP `attributes` array. An empty array, `NULL`, or Drupal `Attribute` object does not produce this variable. |
| `container__additional_classes` | That array's `class` value, or `[]` if absent/null. A supplied class string remains a string; it is not split into an array. |

Conditions that do not assign a variable also do not clear a value supplied by
an earlier preprocessor. The missing-parent tests preserve an existing
`node_title` while adding no new parent metadata.

`FieldHooks::preprocessField()` assigns
`items[*].content['#emulsify_paragraph_index']` only for
`entity_reference_revisions` fields targeting `paragraph`, and only when the
item's `#paragraph` implements the actual Paragraphs `ParagraphInterface`.
Indexes count matching items in render order, beginning at zero. Sparse field
deltas and intervening nonparagraph items do not create gaps. The entity object
is not mutated. Paragraph preprocessing copies that render-array value into
`paragraph_index`.

## Template suggestions

The outputs below append to existing suggestions. They are suggestion keys;
Drupal performs filename selection from those keys.

| Producer | Current appended output |
| --- | --- |
| `FormHooks::themeSuggestionsFormAlter()` | `form__{id}`, selecting `element['#form_id'] ?? element['#id'] ?? NULL`, then replacing hyphens with underscores. A present empty string or `'0'` is falsey and suppresses output rather than falling back to `#id`; `NULL` does fall back. Other punctuation, case, and spaces are retained. Duplicates are retained. |
| `FieldHooks::themeSuggestionsFieldAlter()` | Only for hook name `field`: `field__{entity_type}__{field_name}` if both are truthy; additionally `field__{entity_type}__{field_name}__{bundle}__{view_mode}` if all four are truthy. Values come from `element` keys prefixed with `#`. No hyphen/punctuation normalization and no deduplication. |
| `ViewsHooks::themeSuggestionsViewsViewAlter()` | In order: `views_view__{id}`, `views_view__{id}__{display_type}`, `views_view__{id}__{display}` for truthy required values. Hyphens become underscores in each value; existing exact duplicates are suppressed. Requires an object with `id()`. Display type uses `display_handler->getPluginId()` when available, otherwise `display_handler->display['display_plugin']`; a falsey method result does not fall back to the property. |
| `ViewsHooks::themeSuggestionsViewsViewUnformattedAlter()` | Always appends `views_view_unformatted__{raw_id}` and `views_view_unformatted__{raw_id}__{raw_display}`. It expects a view with `id()` and `current_display`. Hyphens and duplicates remain. Empty strings still produce `views_view_unformatted__` and `views_view_unformatted____`. |
| `ViewsHooks::themeSuggestionsViewsMiniPagerAlter()` | Appends `views_mini_pager__{id}` and `views_mini_pager__{id}__{display}`. Requires an object with `id()` and a nonempty `current_display`; does not require a nonempty view ID. Hyphens become underscores; duplicates remain. |

For example, the main Views wrapper turns `News-View:ID` / `block-one` into
`views_view__News_View:ID__block_one`, while the unformatted style keeps
`views_view_unformatted__News-View:ID__block-one`. This divergence is intentional
in the characterization test and remains unchanged in the runtime hooks.

`ViewsHooks::preprocessViewsView()` also sets `path` to
`view->getRequest()->getPathInfo()` (without its query string). If the incoming
`title` is PHP-empty and `view->getTitle()` is truthy, it sets
`title = ['#markup' => $title]`; otherwise it preserves the incoming title.
The returned title is not escaped by this hook. An incoming string `'0'` counts
as empty under the existing PHP condition.

## PHP and JavaScript ownership

These Drupal preprocess variables and suggestion generators have no JavaScript
counterpart in Emulsify Core. Storybook rendering does not run Drupal's entity,
field, form, or Views hooks. Supply component inputs explicitly when rendering
outside Drupal; do not assume that a Core helper will synthesize these variables.

The paired Twig functions `bem()` and `add_attributes()` belong to Emulsify
Tools on the PHP side and Emulsify Core on the JavaScript side. Their separate
expected outputs and context mutations are recorded in the shared
[Twig helper fixtures](../.github/fixtures/twig-helper-contract.json) and executed
by the [paired helper runner](../.github/scripts/twig-helper-contract.cjs).
The PHP runner loads the published Tools package; a sibling development checkout
is not evidence of the published contract. PHP/JavaScript differences are
asserted separately, never normalized to one shared expected output.

The paired fixtures characterize published Emulsify Tools 2.2.1 and Emulsify
Core 4.4.0. Each case records the returned attribute map, serialized HTML, and
caller context after invocation. These are distinct observations: serialization
can filter values without changing the attribute map.

| Fixture | PHP output versus JavaScript output |
| --- | --- |
| `bem-basic` | Both return `button`; PHP's HTML starts with a space before `class`, while JavaScript's does not. |
| `bem-object-block-element` | `{block: card, element: title, modifiers: [red]}` produces `title__card title__card--red` in PHP and `card__title card__title--red` in JavaScript. |
| `bem-object-aliases` | Camel-case `baseClass` / `blockName` produces no attributes in PHP and `card__button` in JavaScript. |
| `bem-modifier-values` | PHP ignores nested modifier arrays while JavaScript flattens them. Both ignore the false modifier. PHP retains spaces and utility class punctuation; JavaScript splits tokens, changes `u:wide` to `u-wide`, and prefixes `2xl` as `_2xl`. Both keep the numeric zero modifier. |
| `bem-unusual-class` | PHP preserves `Ünicode card/1` as one attribute-map entry; JavaScript produces sanitized/split tokens such as `nicode`, `card-1`, and `_2xl`. See the fixture for their full ordered arrays. |
| `bem-array-context` | Both combine generated and existing classes, but PHP leaves the caller's plain array intact while JavaScript empties its object. Attribute serialization order and leading whitespace differ. |
| `bem-object-context` | Both consume their attribute-object context. PHP serializes `id` before `class`, JavaScript `class` before `id`; PHP retains its leading space. |
| `add-arrays` | Both merge unique classes. PHP appends nonclass array values (including duplicates in the returned map); JavaScript replaces them. Drupal HTML serialization deduplicates the PHP array, and plain-array context consumption differs. |
| `add-false-empty-null` | PHP overwrites an existing boolean with false, skips an empty nonclass string, and returns class strings `true`, `false`, `0`; its HTML omits the false attribute and class string `0`. JavaScript ignores the false attribute update, retains an empty string, and retains only class `true`. Both leave the existing value for a null update. |
| `add-escaping` | PHP preserves numeric attribute type and escapes apostrophes as `&#039;`; JavaScript stringifies the number and leaves apostrophes literal inside double-quoted attributes. Both escape ampersands, double quotes, and angle brackets, consume object context, and emit a true boolean as a bare attribute. PHP alone starts with a space. |
| `bem-empty`, `add-empty` | Both produce an empty attribute map, empty HTML, and empty context. |

Every divergence above has a named `divergence` entry in the shared fixture and
independent `php` and `js` expectations in the tests. No PHP or JavaScript helper
was changed to make these results agree.

## Verification

The [hook characterization script](../.github/scripts/hook-contract-smoke.php)
runs through Drush in a disposable Drupal site. It uses core's real `Node`
interface implementation and published Paragraphs entity/interface for the
indexing branch; simple input objects supply only the methods read by the other
hooks. Entities are never saved and site configuration is not changed.

Install `drupal/paragraphs` as a test-only Composer dependency in that fixture,
then run with its selected PHP runtime:

```bash
vendor/bin/drush php:script /path/to/emulsify-drupal/.github/scripts/hook-contract-smoke.php
```

The script autoloads the installed Paragraphs and Entity Reference Revisions
classes without enabling either module. It fails clearly when they are absent.
The characterized fixture uses Drupal 11.4.6 and Paragraphs 1.23.0 under both
PHP 8.3 and PHP 8.5. Paragraphs 1.23.0 declares Drupal `^10.3 || ^11`, so this
optional hook fixture does not add it to the published-package Drupal 12
installation matrix or change the parent theme's runtime dependencies.
