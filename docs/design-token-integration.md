# Optional Design-Token Integration

Whisk does not ship a default design-token pipeline. Generated child themes get
plain Sass entry points such as `src/tokens.scss`, but they do not assume Figma,
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

Then import the generated Sass from `src/tokens.scss`:

```scss
@use "./tokens.generated";
```

This is only an integration example. Use the token source format, transforms,
and generated asset targets that match the consuming project's design-system
workflow.
