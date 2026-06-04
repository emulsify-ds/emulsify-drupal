import { defineConfig } from 'eslint/config';
import emulsifyCoreConfig from '../../node_modules/@emulsify/core/config/eslint.config.js';

// Extend Emulsify Core's shared flat ESLint config by adding project-specific
// config objects after the spread below.
//
// Example:
// {
//   files: ['src/**/*.js', 'components/**/*.js'],
//   rules: {
//     'no-unused-vars': 'warn',
//   },
// },
export default defineConfig([...emulsifyCoreConfig]);
