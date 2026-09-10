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
Drupal converts underscores to hyphens for template filenames and selects the
last suggestion whose template exists. For example,
`container__class__form_actions` selects
`container--class--form-actions.html.twig`. The orders below are from lower to
higher priority; a missing template falls back to the next available candidate.

Drupal runs module suggestion alters, then base-theme alters, then the active
child theme's alters. A child theme can append its own higher-priority suggestion
or reorder/remove inherited suggestions. Defining the same hook in a child
theme does not replace Emulsify's hook: both run. These additions do not require
Twig Suggest and do not reproduce that module's other suggestions.

| Producer | Current appended output |
| --- | --- |
| `ContainerHooks::themeSuggestionsContainerAlter()` | Class suggestions in supplied order, cumulative structural `#array_parents` paths, a `data-drupal-selector` suggestion, then an ID suggestion. Hyphens become underscores; duplicates remain. See [Container suggestions](#container-suggestions). |
| `FormHooks::themeSuggestionsFormAlter()` | A shared Layout Builder fallback when applicable, then `form__{id}`, then Views exposed-form variants and a placed-block variant when applicable. The existing ID selection remains `element['#form_id'] ?? element['#id'] ?? NULL`, followed by hyphen-to-underscore replacement. A present empty string or `'0'` is falsey and suppresses output rather than falling back to `#id`; `NULL` does fall back. Other punctuation, case, and spaces are retained. Duplicates are retained. See [Form suggestions](#form-suggestions). |
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

### Container suggestions

Container suggestions read `variables['element']`. This example combines
classes `['form-actions', 'compact']`, structural parents
`['advanced', 'actions']`, selector `edit-actions`, and ID `checkout-actions`:

| Appended order | Suggested filename |
| --- | --- |
| First class | `container--class--form-actions.html.twig` |
| Second class | `container--class--compact.html.twig` |
| First structural ancestor | `container--parents--advanced.html.twig` |
| Full structural path | `container--parents--advanced--actions.html.twig` |
| Drupal selector | `container--selector--edit-actions.html.twig` |
| ID, highest priority | `container--id--checkout-actions.html.twig` |

Classes come from `#attributes['class']`; a class string is treated as one
entry, not split on spaces. Empty-string classes are skipped. Paths use
`#array_parents`, preserving the form's render structure even when `#tree` is
false and value-oriented `#parents` is flattened. Each additional ancestor
produces a more specific candidate, including numeric path segments.

A nonempty `#attributes['data-drupal-selector']` supplies the selector. Drupal
keeps that selector stable when it adds a unique suffix to a form element's DOM
ID, making selector templates useful for repeated form instances. The ID comes
from `#attributes['id']`; when that is absent or null, `#id` is used only if
`#array_parents` is set, matching Drupal's form-container preprocessing. A
present empty-string attribute ID suppresses the ID suggestion. Only hyphens
are normalized; other punctuation, case, and spaces remain unchanged.

### Form suggestions

These suggestions target the outer `<form>` wrapper. Add overrides alongside
your child theme's other templates, preserve the parent
[`form.html.twig`](../templates/form/form.html.twig) attributes and children,
then rebuild Drupal caches. No templates need to be copied to keep the default
rendering.

Layout Builder forms with a real `#form_id` ending in `_layout_builder_form`
or beginning with `layout_builder_` receive
`form--layout-builder-form.html.twig` as a shared fallback. The exact form-ID
suggestion follows it and takes precedence; for example,
`form--layout-builder-add-block.html.twig` wins over the shared fallback for
`#form_id = layout_builder_add_block`. A DOM `#id` matching those patterns does
not classify an otherwise unidentified form as a Layout Builder form.

Forms whose real `#form_id` is `views_exposed_form` receive the following
suggestions. For a View named `news`, page display `page_1`, and configured
block placement `header_search`, representative candidates are:

| Appended order | Suggested filename |
| --- | --- |
| Generic exposed form | `form--views-exposed-form.html.twig` |
| View | `form--views-exposed-form--news.html.twig` |
| Display type | `form--views-exposed-form--page.html.twig` |
| View and display type | `form--views-exposed-form--news--page.html.twig` |
| Display | `form--views-exposed-form--page-1.html.twig` |
| View and display | `form--views-exposed-form--news--page-1.html.twig` |
| Placed block, highest priority | `form--views-exposed-form--block--header-search.html.twig` |

The hook reuses the form's `#theme` candidates that begin with
`views_exposed_form__`, reverses their supplied order, and prefixes `form__`.
Views supplies those candidates from most to least specific; reversing them
preserves their priority in an alter hook. Display-type and tag candidates,
including their order and existing normalization, come from Views rather than
being reconstructed here. The table omits tags for clarity. A missing `#theme`
adds no View-specific candidates. A DOM `#id` of `views_exposed_form` alone
still gets the existing generic ID suggestion, but no Views-specific variants.

`FormHooks::preprocessBlock()` copies a nonempty `elements['#id']` to
`content['#emulsify_block_id']` only when that content's `#form_id` is
`views_exposed_form`. The form hook uses this value for the final block-specific
candidate, replacing hyphens with underscores. This identifies a configured
block placement, not the Views block plugin ID. It is available only when the
block render array supplies `#id`; Layout Builder placements do not necessarily
supply one, and no Layout Builder UUID is inferred.

`form--views-exposed-form--…` overrides wrap the entire form, including its
attributes. `views-exposed-form--…` overrides still target the inner exposed
filter layout. When a child theme already implements equivalent container or
form hooks, review its appended order before removing duplicate logic: a later
child-theme generic suggestion can otherwise outrank an inherited specific one.

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
hooks. Direct checks cover form `#id` fallback compatibility, empty/null values,
preserved duplicates, container paths and attribute precedence, exposed-form
candidate order, and placed-block propagation.

The same script also exercises Drupal's real theme manager and renderer with
temporary in-memory Twig marker templates and theme registry entries. It checks
which template wins and which fallback renders when more specific candidates
are unavailable, covering container priorities, Layout Builder forms, Views
exposed forms, and separate block placements. The temporary Twig loader and
registry entries are restored after these checks. Entities are never saved and
site configuration is not changed.

The expanded suite passes 170 assertions on Drupal 11.3.16 / PHP 8.3.33
(Emulsify Tools 2.2.1, Paragraphs 1.23.0) and Drupal 11.4.4 / PHP 8.5.10
(Emulsify Tools 2.2.0, Paragraphs 1.21.0).

Install `drupal/paragraphs` as a test-only Composer dependency in that fixture,
then run with its selected PHP runtime:

```bash
vendor/bin/drush php:script /path/to/emulsify-drupal/.github/scripts/hook-contract-smoke.php
```

The script autoloads the installed Paragraphs and Entity Reference Revisions
classes without enabling either module. It fails clearly when they are absent.
The original characterization used Drupal 11.4.6 and Paragraphs 1.23.0 under both
PHP 8.3 and PHP 8.5. Paragraphs 1.23.0 declares Drupal `^10.3 || ^11`, so this
optional hook fixture does not add it to the published-package Drupal 12
installation matrix or change the parent theme's runtime dependencies.
