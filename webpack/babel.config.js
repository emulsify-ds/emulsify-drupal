module.exports = (api) => {
  api.cache(true);

  const presets = [
    [
      '@babel/preset-env', {
        useBuiltIns: 'entry'
      },
    ],
    'minify',
  ];

  const comments = false

  return { presets, comments };
};
