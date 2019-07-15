const path = require('path');
const _MiniCssExtractPlugin = require('mini-css-extract-plugin');
const _StyleLintPlugin = require('stylelint-webpack-plugin');

const MiniCssExtractPlugin = new _MiniCssExtractPlugin({
  filename: '[name].css',
});

const StyleLintPlugin = new _StyleLintPlugin({
  configFile: path.resolve(__dirname, '.stylelintrc'),
  context: path.resolve(__dirname, '../components'),
  files: '**/*.css',
  failOnError: false,
  quiet: false,
});

module.exports = {
  MiniCssExtractPlugin: MiniCssExtractPlugin,
  StyleLintPlugin: StyleLintPlugin
};
