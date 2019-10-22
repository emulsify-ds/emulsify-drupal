const path = require('path');
const loaders = require('./loaders');
const plugins = require('./plugins');

const webpackDir = path.resolve(__dirname);
const rootDir = path.resolve(__dirname, '..');
const distDir = path.resolve(rootDir, 'dist');

module.exports = {
  entry: {
    svgSprite: path.resolve(webpackDir, 'svgSprite.js'),
    css: path.resolve(webpackDir, 'css.js'),
  },
  module: {
    rules: [loaders.CSSLoader, loaders.SVGSpriteLoader],
  },
  plugins: [
    plugins.StyleLintPlugin,
    plugins.MiniCssExtractPlugin,
    plugins.ImageminPlugin,
    plugins.SpriteLoaderPlugin,
    plugins.ProgressPlugin,
    plugins.CleanWebpackPlugin,
  ],
  output: {
    path: distDir,
    filename: 'remove/[name].js',
  },
};
