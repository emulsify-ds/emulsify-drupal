# Upgrading %%EMULSIFY_THEME_NAME%%

This project was generated from `%%EMULSIFY_SOURCE_PROJECT%%` `%%EMULSIFY_SOURCE_VERSION%%` and currently expects `@emulsify/core` `%%EMULSIFY_CORE_RANGE%%`.

## Choose the upgrade type

An npm dependency update changes packages within this project. Adopting a newer starter release is a manual comparison against a newly generated theme. Neither operation should replace project-owned templates, components, stories, styles, or tests without review.

### Update npm dependencies

1. Create a project branch and make sure the current build is green.
2. Review available package updates and the Emulsify Core release notes.
3. Update only the intended dependencies and commit the resulting `package-lock.json`.
4. Review changes to transitive packages and rerun the validation commands below.

The range in `package.json` describes compatible Emulsify Core releases; it is not a promise that every future release requires no project changes.

### Compare with a newer starter release

1. Check out the intended Emulsify Drupal release separately.
2. Use Drupal's `generate-theme` command to create a fresh, temporary comparison theme with a different machine name.
3. Compare the fresh theme with this project, concentrating on:
   - `package.json`, `project.emulsify.json`, and `.nvmrc`;
   - `config/emulsify-core/` plus Vite, Storybook, Jest, lint, and formatting scripts;
   - the Sass entrypoints and Drupal library output paths;
   - theme info, settings schema, breakpoints, and templates;
   - this README, upgrade guidance, and support checklist.
4. Port only changes that benefit the project. Resolve them against local customizations instead of copying the fresh theme over this directory.
5. Delete the temporary comparison theme after the review.

Starter releases do not provide an automatic project diff or migration. The fresh generation is evidence for a human-reviewed comparison.

## Validate the result

From this theme directory:

```bash
npm install
npm run lint
npm run test
npm run build
npm run storybook-build
```

Also enable and render the theme in the project's supported Drupal environment when Drupal metadata, Twig templates, components, or library declarations changed.

## Preserve source history

Keep `project.generatedFrom` and `project.generatedFromVersion` in `project.emulsify.json`. They document the baseline originally used for generation. Update them only when the project deliberately adopts a newer starter baseline and records that decision in version control.

The current recorded source is `%%EMULSIFY_SOURCE_PROJECT%%` `%%EMULSIFY_SOURCE_VERSION%%`; the current Emulsify Core range is `%%EMULSIFY_CORE_RANGE%%`.
