# Twig Component Includes

Prefer Drupal Single Directory Component (SDC) names when including project
components from Twig in new Emulsify Drupal projects.

Use the generated theme machine name as the component namespace and the
component directory name after the colon:

```twig
{% include "my_theme:list" with {
  items: items,
} only %}
```

The Twig function form is also supported:

```twig
{{ include("my_theme:list", {
  items: items,
}, with_context = false) }}
```

For a generated project named `my_theme`, a component stored at
`components/list/list.twig` is referenced as `my_theme:list`. Replace
`my_theme` with the generated project machine name from
`project.emulsify.json`.

Legacy Twig namespace includes still work and remain valid for existing
projects, shared templates, and migration work:

```twig
{% include "@components/button/button.twig" with {
  label: label,
} only %}
```

Do not use the legacy namespace form as the default pattern for new project
components. It depends on `components.namespaces` mappings in the theme info
file, while SDC component names are the Drupal-native component reference used
by current Emulsify Drupal projects.
