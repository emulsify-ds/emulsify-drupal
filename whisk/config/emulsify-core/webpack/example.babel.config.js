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
