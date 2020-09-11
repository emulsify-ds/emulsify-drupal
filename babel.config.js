module.exports = (api) => {
  api.cache(true);

  const presets = [
    [
      '@babel/preset-env',
      {
        corejs: 3,
        useBuiltIns: 'entry',
      },
    ],
    // 'minify',
  ];

  const comments = false;

  return { presets, comments };
};
