const merge = require('webpack-merge');
const common = require('./webpack.common.js');

const loaders = require('./loaders');

module.exports = merge(common, {
  mode: 'development',
  module: {
    rules: [loaders.CSSDevLoader],
  },
  devtool: 'inline-source-map',
});
