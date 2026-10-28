---
name: emulsify-drupal-integration
description: Integrate or debug project components in an Emulsify Drupal child theme, including template selection, Drupal data mapping, and asset attachment. Use when connecting components to Drupal rendering; component-only styling or story changes do not require this workflow.
---

# Emulsify Drupal integration

Read the theme's `AGENTS.md` and
[Drupal integration guide](../../../docs/drupal-integration.md). Resolve paths
from this skill's directory, then work from the theme root.

If the theme is a generation source, follow its enclosing repository's workflow
and verify the generated result. For a generated theme:

1. Read local Drupal and project metadata, npm scripts, installed library
   guidance, and the closest existing integration. Discover the component's
   actual API and supported references from its schema, stories, and source.
   Use the component inspector to locate Twig templates when available.
2. Confirm the target entity, bundle, view mode, fields, access rules, and active
   template suggestion in Drupal. Inspect inherited templates and hooks before
   adding a project override or data transformation.
3. Map Drupal values to the component's documented props or slots, preserving
   render arrays, attributes, contextual editing, translations, access results,
   and cache metadata. Keep project field mappings in the project and follow
   the library's asset integration contract.
4. Run applicable checks from the theme's scripts, rebuild Drupal caches when
   needed, and render the affected page in Drupal. Check representative empty,
   populated, translated, and access-restricted cases relevant to the change.

Report the template/component used, the mapping or attachment changed, checks
run, and any Drupal verification that remains unavailable. A passing story
does not exercise Drupal preprocessing, access checks, or library attachment.
