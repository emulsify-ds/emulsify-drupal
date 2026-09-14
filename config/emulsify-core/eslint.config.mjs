import emulsifyCoreConfig from '../../node_modules/@emulsify/core/config/eslint.config.js';

export default [
  ...emulsifyCoreConfig,
  {
    files: ['assets/**/*.js'],
    languageOptions: {
      globals: { Drupal: 'readonly', once: 'readonly' },
    },
    rules: { 'no-undef': 'error' },
  },
];
