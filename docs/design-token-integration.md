# Optional Design-Token Integration

The `whisk` starterkit source does not ship a default design-token pipeline.
Drupal Whisk keeps minimal Sass entry points (`src/tokens.scss`,
`src/foundation.scss`, and `src/layout.scss`) because `whisk.libraries.yml`
maps their generated Vite output to `dist/global/*.css`. Those files are
library build entry points, not an assumption that every project uses Figma,
Style Dictionary, Token Transformer, or any other token source.

Projects that already use design tokens can add their preferred pipeline to the
generated child theme. Keep those dependencies and scripts in the child theme so
teams that do not use design tokens are not required to install unused tooling.

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

If the project keeps `src/tokens.scss` as the token entry point, import the
generated Sass there:

```scss
@use "./tokens.generated";
```

This is only an integration example. Use the token source format, transforms,
and generated asset targets that match the consuming project's design-system
workflow.
