const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const globImporter = require('node-sass-glob-importer');

const JSLoader = {
  test: /^(?!.*\.(stories|component)\.js$).*\.js$/,
  exclude: /node_modules/,
  loader: 'babel-loader',
};

const ImageLoader = {
  test: /\.(png|svg|jpg|gif)$/i,
  exclude: /icons\/.*\.svg$/,
  loader: 'file-loader',
};

const CSSLoader = {
  test: /\.s[ac]ss$/i,
  exclude: /node_modules/,
  use: [
    MiniCssExtractPlugin.loader,
    {
      loader: 'css-loader',
      options: {
        sourceMap: true,
        url: false,
      },
    },
    {
      loader: 'postcss-loader',
      options: {
        sourceMap: true,
      },
    },
    {
      loader: 'sass-loader',
      options: {
        sourceMap: true,
        additionalData: `@import "${path.resolve(
          __dirname,
          '../components/_import.scss',
        )}";`,
        sassOptions: {
          importer: globImporter(),
          outputStyle: 'compressed',
        },
      },
    },
  ],
};

const SVGSpriteLoader = {
  test: /icons\/.*\.svg$/, // your icons directory
  loader: 'svg-sprite-loader',
  options: {
    extract: true,
    spriteFilename: '../dist/icons.svg',
  },
};

const FontLoader = {
  test: /.(woff|woff2|ttf|eot|otf|svg)$/,
  loader: 'file-loader',
  include: [/fonts/],
};

module.exports = {
  JSLoader,
  CSSLoader,
  SVGSpriteLoader,
  ImageLoader,
  FontLoader,
};
