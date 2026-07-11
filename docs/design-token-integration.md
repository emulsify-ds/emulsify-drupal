# Optional Design-Token Integration

The `whisk` starterkit source generates no project asset source tree,
entrypoints, Drupal asset libraries, or design-token pipeline. The component
library selected by the project owns those decisions.

Projects that use design tokens can add their preferred pipeline to the
generated child theme. Keep those dependencies and scripts in the child theme
so teams that do not use design tokens are not required to install unused
tooling. The example below uses `src/tokens` only as an illustration; adapt all
paths to the selected component library.

## Example: Style Dictionary

Install Style Dictionary in the generated child theme:

```bash
npm install --save-dev style-dictionary
```

Add a project-owned config such as `config/tokens/style-dictionary.config.mjs`:

```js
export default {
  source: ['src/tokens/**/*.tokens.json'],
  platforms: {
    scss: {
      transformGroup: 'scss',
      buildPath: 'src/',
      files: [
        {
          destination: 'tokens.generated.scss',
          format: 'scss/variables',
        },
      ],
    },
  },
};
```

Add project scripts:

```json
{
  "scripts": {
    "tokens:build": "style-dictionary build --config config/tokens/style-dictionary.config.mjs",
    "build": "npm run tokens:build && npm run ensure-dist && vite build --config node_modules/@emulsify/core/config/vite/vite.config.js"
  }
}
```

Import the generated Sass from the component library's project-owned entrypoint:

```scss
@use "./tokens.generated";
```

This is only an integration example. Use the token source format, transforms,
and generated asset targets that match the consuming project's design-system
workflow.
