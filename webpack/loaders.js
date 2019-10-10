const path = require('path');
const MiniCssExtractPlugin = require("mini-css-extract-plugin");

const JSLoader = {
  test: /\.js/,
  use: {
    loader: 'babel-loader',
    options: {
      presets: ['@babel/preset-env']
    }
  }
};

const CSSLoader = {
  test: /\.css$/,
  exclude: /node_modules/,
  use: [
    MiniCssExtractPlugin.loader,
    { loader: 'css-loader', options: {
      importLoaders: 1,
      sourceMap: true
    } },
    { loader: 'postcss-loader', options: {
      config: {
        path: path.resolve("./webpack/")
      },
      sourceMap: true
    } },
  ],
};

const SVGSpriteLoader = {
  test: /icons\/.*\.svg$/, // your icons directory
  loader: 'svg-sprite-loader',
  options: {
    extract: true,
    spriteFilename: '../dist/icons.svg',
  }
};

module.exports = {
  JSLoader: JSLoader,
  CSSLoader: CSSLoader,
  SVGSpriteLoader: SVGSpriteLoader,
};
