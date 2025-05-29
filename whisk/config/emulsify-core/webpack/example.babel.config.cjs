// Change the name of this file to babel.config.js
// for Emulsify Core to recognize and fully override
// its provided configuration.
module.exports = (api) => {
  api.cache(true);

  const presets = [
    [
      '@babel/preset-env',
      {
        useBuiltIns: 'entry',
        corejs: 3,
      },
    ],
    ['minify', { builtIns: false }],
  ];

  const comments = false;

  return { presets, comments };
};
