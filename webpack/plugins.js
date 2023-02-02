/* eslint-disable no-underscore-dangle */
const path = require('path');
const webpack = require('webpack');
const { CleanWebpackPlugin } = require('clean-webpack-plugin');
const _MiniCssExtractPlugin = require('mini-css-extract-plugin');
const _ImageminPlugin = require('imagemin-webpack-plugin').default;
const _SpriteLoaderPlugin = require('svg-sprite-loader/plugin');
const glob = require('glob');

const _StyleLintPlugin = require('stylelint-webpack-plugin');
const _ESLintPlugin = require('eslint-webpack-plugin');

const imagePath = path.resolve(__dirname, '../images');

const MiniCssExtractPlugin = new _MiniCssExtractPlugin({
  filename: '[name].css',
  chunkFilename: '[id].css',
});

const ImageminPlugin = new _ImageminPlugin({
  disable: process.env.NODE_ENV !== 'production',
  externalImages: {
    context: imagePath,
    sources: glob.sync(path.resolve(imagePath, '**/*.{png,jpg,gif,svg}')),
    destination: imagePath,
  },
});

const SpriteLoaderPlugin = new _SpriteLoaderPlugin({
  plainSprite: true,
});

const ProgressPlugin = new webpack.ProgressPlugin();

const StyleLintPlugin = new _StyleLintPlugin({
  configFile: path.resolve(__dirname, '../', '.stylelintrc'),
  context: path.resolve(__dirname, '../', 'components'),
  files: '**/*.scss',
});

const ESLintPlugin = new _ESLintPlugin({
  context: path.resolve(__dirname, '../', 'components'),
  extensions: ['js'],
});

module.exports = {
  ProgressPlugin,
  MiniCssExtractPlugin,
  ImageminPlugin,
  SpriteLoaderPlugin,
  CleanWebpackPlugin: new CleanWebpackPlugin({
    protectWebpackAssets: false, // Required for removal of extra, unwanted dist/css/*.js files.
    cleanOnceBeforeBuildPatterns: ['!*.{png,jpg,gif,svg}'],
    cleanAfterEveryBuildPatterns: [
      'remove/**',
      '!js',
      'css/**/*.js', // Remove all unwanted, auto generated JS files from dist/css folder.
      'css/**/*.js.map',
      '!*.{png,jpg,gif,svg}',
    ],
  }),
  StyleLintPlugin,
  ESLintPlugin,
};
