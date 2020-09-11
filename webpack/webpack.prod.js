const merge = require('webpack-merge');
const common = require('./webpack.common.js');

const loaders = require('./loaders');
const plugins = require('./plugins');

module.exports = merge(common, {
  mode: 'production',
  module: {
    rules: [loaders.CSSDevLoader],
  },
  plugins: [plugins.MiniCssExtractPlugin],
});
