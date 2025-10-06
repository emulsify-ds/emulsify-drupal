// Change the name of this file to plugins.js
// for Emulsify Core to recognize and fully override
// its provided configuration.

/**
 * Export a function that returns extra Vite plugins.
 * You can import any Vite plugin here (e.g., vite-plugin-inspect).
 */
// import Inspect from 'vite-plugin-inspect';

export default function projectPlugins(ctx) {
  // const { env } = ctx; // env is what resolveEnvironment() returned
  return [
    // Inspect(), // uncomment if you want Vite Inspector
  ];
}

/**
 * Option B: Patch the final Vite config.
 * Great for enabling Tailwind by setting a PostCSS config path.
 *
 * You can also inject other config tweaks here (aliases, define, etc.)
 */
export function extendConfig(base, { env }) {
  return {
    css: {
      // Point PostCSS to the project's Tailwind config if present:
      // (You can also keep a root-level postcss.config.*; this overrides it.)
      postcss: './.config/emulsify-core/vite/postcss.config.cjs',
    },
  };
}
