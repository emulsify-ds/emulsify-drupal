// Rename this file to babel.config.cjs only when a project-specific Vite plugin
// or build tool is configured to load Babel.

module.exports = (api) => {
  api.cache(true);

  return {
    presets: [
      // Example:
      //
      // [
      //   '@babel/preset-env',
      //   {
      //     useBuiltIns: 'entry',
      //     corejs: 3,
      //   },
      // ],
      // ['minify', { builtIns: false }],
    ],
    comments: false,
  };
};
