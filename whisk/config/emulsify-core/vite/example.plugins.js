// Rename this file to plugins.js, plugins.mjs, or plugins.cjs when the project
// needs to extend Emulsify Core's Vite config.
//
// This file is intentionally a no-op example. Uncomment only the pieces your
// project needs.

// Example: add Vite plugins from project dependencies.
//
// import Inspect from 'vite-plugin-inspect';
//
// export default function projectPlugins({ env }) {
//   return [
//     Inspect(),
//   ];
// }

export default [];

// Example: patch the final Vite config after Emulsify Core assembles it.
// Prefer plugin exports for normal framework integration; use extendConfig()
// when a Vite option cannot be expressed through a plugin.
//
// export function extendConfig(config, { env }) {
//   return {
//     css: {
//       postcss: './config/emulsify-core/vite/postcss.config.cjs',
//     },
//   };
// }
