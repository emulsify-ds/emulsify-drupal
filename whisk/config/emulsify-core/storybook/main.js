// This file is loaded by Emulsify Core's shared Storybook main config.
//
// Keep the default export as an object. Emulsify Core shallow-merges this object
// into its default Storybook config. Addons are handled specially: project
// addons are appended to Emulsify Core's defaults unless replaceAddons is true.
//
// This file is for Node-side Storybook configuration, such as:
// - addons
// - static asset directories
// - final Storybook config patches
//
// Browser-side story parameters belong in preview.js.
// Storybook manager branding belongs in theme.js.

// Pass empty config overrides by default so generated themes inherit Emulsify
// Core's stories, framework, Vite builder, Twig handling, and default addons.
const configOverrides = {};

// Example: add a project-specific addon and static directory.
//
// Install the addon in the generated theme first:
// npm install --save-dev @storybook/addon-viewport
//
// const configOverrides = {
//   addons: ['@storybook/addon-viewport'],
//   staticDirs: ['public'],
// };

// Example: customize an Emulsify Core default addon without duplicating it.
//
// const configOverrides = {
//   addons: [
//     {
//       name: '@storybook/addon-a11y',
//       options: {
//         manual: true,
//       },
//     },
//   ],
// };

// Example: replace the full addon list instead of appending to Core defaults.
// Use this only when the project intentionally opts out of Emulsify defaults.
//
// export const replaceAddons = true;
//
// const configOverrides = {
//   addons: ['@storybook/addon-viewport'],
// };

// Example: patch the final Storybook config after Emulsify Core applies the
// default export above. The env object is the normalized project.emulsify.json
// model resolved by Emulsify Core.
//
// export function extendConfig(config, { env }) {
//   return {
//     ...config,
//     staticDirs:
//       env.platform === 'drupal'
//         ? [...(config.staticDirs || []), 'storybook-static-assets']
//         : config.staticDirs,
//   };
// }

// Avoid redefining stories, framework, core.builder, or viteFinal unless the
// project is intentionally replacing Emulsify Core's Storybook integration.

export default configOverrides;
