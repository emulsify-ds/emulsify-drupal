const path = require('path');
const merge = require('webpack-merge');
const common = require('./webpack.common.js');
const loaders = require('./loaders');
const plugins = require('./plugins');

const webpackDir = path.resolve(__dirname);

module.exports = merge(common, {
  mode: 'production',
  entry: {
    css: path.resolve(webpackDir, 'css.js'),
  },
  module: {
    rules: [loaders.CSSLoader],
  },
  plugins: [
    plugins.MiniCssExtractPlugin,
  ],
});
