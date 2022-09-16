const path = require('path');
const glob = require('glob');
const loaders = require('./loaders');
const plugins = require('./plugins');

const webpackDir = path.resolve(__dirname);
const rootDir = path.resolve(__dirname, '..');
const distDir = path.resolve(rootDir, 'dist');

function getEntries(pattern, patternCss) {
  const entries = {};

  glob.sync(pattern).forEach((file) => {
    const filePath = file.split('components/')[1];
    const newfilePath = `js/${filePath.replace('.js', '')}`;
    entries[newfilePath] = file;
  });

  glob.sync(patternCss).forEach((file) => {
    const filePath = file.split('components/')[1];
    const newfilePath = `css/${filePath.replace('.component.scss', '')}`;
    entries[newfilePath] = file;
  });

  entries.svgSprite = path.resolve(webpackDir, 'svgSprite.js');
  entries.global = path.resolve(webpackDir, 'global.js');

  return entries;
}

module.exports = {
  stats: {
    errorDetails: true,
  },
  entry: getEntries(
    path.resolve(
      rootDir,
      'components/**/!(*.stories|*.component|*.min|*.test).js',
    ),
    path.resolve(rootDir, 'components/**/*.component.scss'),
  ),
  module: {
    rules: [
      loaders.CSSLoader,
      loaders.SVGSpriteLoader,
      loaders.ImageLoader,
      loaders.JSLoader,
      loaders.FontLoader,
    ],
  },
  plugins: [
    plugins.MiniCssExtractPlugin,
    plugins.ImageminPlugin,
    plugins.SpriteLoaderPlugin,
    plugins.ProgressPlugin,
    plugins.CleanWebpackPlugin,
    plugins.StyleLintPlugin,
    plugins.ESLintPlugin,
  ],
  output: {
    path: distDir,
    filename: '[name].js',
  },
};
