const path = require('path');
const webpack = require('webpack');
const { CleanWebpackPlugin } = require('clean-webpack-plugin');
const _MiniCssExtractPlugin = require('mini-css-extract-plugin');
const _StyleLintPlugin = require('stylelint-webpack-plugin');
const _ImageminPlugin = require('imagemin-webpack-plugin').default
const _SpriteLoaderPlugin = require('svg-sprite-loader/plugin');
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
    sources: glob.sync(path.resolve(imagePath, '**/*.{png,jpg,gif}')),
    destination: imagePath,
  }
});

const SpriteLoaderPlugin = new _SpriteLoaderPlugin({
  plainSprite: true
});

const ProgressPlugin = new webpack.ProgressPlugin();

module.exports = {
  ProgressPlugin: ProgressPlugin,
  MiniCssExtractPlugin: MiniCssExtractPlugin,
  StyleLintPlugin: StyleLintPlugin,
  ImageminPlugin: ImageminPlugin,
  SpriteLoaderPlugin: SpriteLoaderPlugin,
  CleanWebpackPlugin: new CleanWebpackPlugin({
    cleanAfterEveryBuildPatterns: ['remove/**'],
  }),
};
