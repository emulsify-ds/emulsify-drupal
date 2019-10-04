const path = require('path');
const _MiniCssExtractPlugin = require('mini-css-extract-plugin');
const _StyleLintPlugin = require('stylelint-webpack-plugin');
const _ImageminPlugin = require('imagemin-webpack-plugin').default
const glob = require('glob')

const imagePath = path.resolve(__dirname, '../images');

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

const ImageminPlugin = new _ImageminPlugin({
  // disable: process.env.NODE_ENV !== 'production', // Disable during development
  externalImages: {
    context: imagePath,
    sources: glob.sync(path.resolve(imagePath, '**/*')),
    destination: imagePath,
  }
});

module.exports = {
  MiniCssExtractPlugin: MiniCssExtractPlugin,
  StyleLintPlugin: StyleLintPlugin,
  ImageminPlugin: ImageminPlugin
};
