const path = require('path');
const MiniCssExtractPlugin = require("mini-css-extract-plugin");

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
  CSSLoader: CSSLoader,
  SVGSpriteLoader: SVGSpriteLoader,
};
