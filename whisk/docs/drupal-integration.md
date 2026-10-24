# Integrate project components with Drupal

This guide describes the Drupal side of an Emulsify child theme. Use the
installed component library's instructions for component layout, APIs, tokens,
entrypoints, build output, and asset attachment. The project supplies its own
content types, fields, view modes, and mappings to those components.

## Discover the project and component

Work from the generated theme root. Read `project.emulsify.json`, `package.json`,
the theme's `.info.yml`, existing templates, and relevant preprocessing code.
The `.info.yml` filename supplies the Drupal theme machine name; compare it
with project metadata. Preserve `generatedFrom` and `generatedFromVersion`.

If a `*.starterkit.yml` exists, this directory is a generation source rather
than a generated project theme. Follow the enclosing repository's development
workflow and check the generated output before treating a source change as a
working project integration.

When present, `system` and `variant.structureImplementations` in project
metadata identify the selected library and declared source roots. Read its
installed guide, conventionally `docs/component-library.md`, and local
component instructions. Existing project files remain the evidence for local
changes. Do not infer directories or namespaces from another Emulsify project.

After npm dependencies are installed, use the available inventory command:

```bash
npm run inspect:components -- --json
```

The inspector locates non-partial Twig templates recognized by Emulsify Core
and reports source/output locations, template references, and namespace
collisions. An empty starter returns a valid empty inventory. The inventory
does not describe the complete component API or prove Drupal registration.
Read the component's schema, stories, Twig or JavaScript, and tests together to
establish required inputs, slots, nesting, and rendering behavior.

`project.singleDirectoryComponents` enables Core's SDC integration; it does
not make every template an SDC. For an actual Drupal SDC, confirm its metadata
and discovery by Drupal before using the theme machine name and component name
as a `theme_name:component_name` reference. For plain Twig components, confirm
the installed namespace mapping and exact template path. React components
need the project's Drupal mounting and asset strategy. Follow the component
type already selected by the project.

## Select the Drupal extension point

Identify the actual entity type, bundle, view mode, field names, field display,
and language. Inspect site configuration and the rendered page rather than
assuming that an example field exists. Drupal Twig debug output or a theme
registry inspection can confirm the active template and available suggestions.
Use the site's development tooling to enable debugging; keep those settings
out of production configuration.

Locate the installed parent theme using Drupal theme discovery or Composer
installation paths. Inspect its matching template, `src/Hook/`, and, when
available, `docs/template-extension.md` and `docs/twig-hook-contract.md` in that
installed theme. Parent paths vary between sites; this generated guide does
not rely on a fixed relative path to the parent repository.

Emulsify's parent hooks provide preprocessing and additional template
suggestions. Base-theme and child-theme suggestion alters both run; a child
hook adds to inherited behavior rather than replacing the parent hook.
Confirm the resulting suggestion order before adding duplicate logic. Drupal
converts suggestion underscores to template filename hyphens and chooses the
highest-priority available candidate. An override may use a normal core
suggestion, an inherited suggestion, or a project-specific suggestion.

Overrides of `form-element`, `fieldset`, `details`, `datetime-wrapper`, or
`form-element--new-storage-type` must keep
`id="{{ element['#id'] }}--error"` on the wrapper around `{{ errors }}`,
including through component mappings. With Inline Form Errors enabled, parent
hooks reference this ID regardless of the selected override. See "Inline form
error references" in the installed parent's `docs/twig-hook-contract.md`.

For page, HTML, region, or base block changes, prefer the parent's documented
Twig extension blocks when they cover the requirement. For example, a local
page override can preserve the page wrapper while adding content:

```twig
{% extends "@emulsify/templates/layout/page.html.twig" %}

{% block page_content %}
  {{ parent() }}
  <p>{{ 'Thank you for visiting.'|t }}</p>
{% endblock %}
```

The explicit `@emulsify` path selects the parent template. Inspect the installed
version's extension blocks before using them. Preserve Drupal head and
JavaScript placeholders, the skip link and target, regions, form children,
labels, and error associations when replacing surrounding markup.

## Map Drupal data without losing rendering behavior

Keep the adapter in the project's template or preprocessing layer; select the
existing project pattern for PHP hooks or entity helpers. The component library
defines inputs, while the project decides which Drupal fields supply them.

- Pass field render arrays to content-capable props or slots when the component
  accepts them. This preserves formatters, text filtering, attachments, access
  handling, and cache metadata. Raw `.value` extraction can bypass that work.
- If a component requires a scalar or URL, derive it through Drupal APIs in the
  appropriate language and preserve access results and cache tags, contexts,
  and max-age on the render array receiving the derived value. Escape according
  to the output context; avoid `raw` for untrusted text and formatted fields.
- Preserve `attributes` and applicable `title_attributes`/`content_attributes`
  on the correct wrappers. Retain `title_prefix` and `title_suffix`, including
  contextual editing links, when replacing entity or block markup.
- Render content not intentionally consumed by the component. When using
  `content|without(...)`, exclude only the mapped fields and intentional
  omissions so remaining fields and attached behavior continue to render.
- Pass explicit inputs when using Twig `only` or disabling include context.
  Include any Drupal attributes, title wrappers, or metadata the component
  expects; isolated context does not supply them automatically.
- Keep translatable labels translatable and use translated entity/field data.
  Preserve keyboard interaction, accessible names, headings, and focus behavior
  while changing markup.

Storybook does not execute Drupal entity, field, form, or Views preprocessing.
Supply story inputs explicitly. Helpers such as `bem()` and `add_attributes()`
have PHP and JavaScript implementations whose behavior can differ; verify the
relevant behavior in Drupal before relying on a story result.

## Attach and verify assets

Follow the library's declared build and Drupal attachment strategy. Confirm
that `.libraries.yml` references actual emitted files and that global,
template-level, or SDC attachments load them on the affected page. The starter
ships no active project asset references. Use project overrides in
`config/emulsify-core/` to extend shared tooling; keep changes out of installed
`node_modules` packages.

Use commands declared in this theme's `package.json` and checks appropriate to
the change. Component inspection and lint can run before library sources are
present. Build, Storybook, and accessibility commands need the library's build
inputs; the default test command fails when no tests exist. See
[the development guide](development.md) for the command workflow.

After template or discovery changes, rebuild caches using the site's configured
Drush command, commonly `drush cr`, from the Drupal project. Render the actual
target page and confirm the selected override, field output, contextual links,
asset loading, and interaction. Test empty and populated fields, multiple
values, translations, and relevant user permissions. Check keyboard operation
and accessible output for the affected behavior. A successful static story or
frontend build cannot establish these Drupal results.

If the site or required tooling is unavailable, report the checks completed
and the exact Drupal behavior still unverified.
