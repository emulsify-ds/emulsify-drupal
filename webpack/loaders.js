const path = require('path');
const MiniCssExtractPlugin = require("mini-css-extract-plugin");

const JSLoader = {
  test: /^(?!.*\.component\.js$)(?!.*\.stories\.js$).*\.js$/,
  include: [
    path.resolve(__dirname, "components/02-molecules")
  ],
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

module.exports = {
  JSLoader: JSLoader,
  CSSLoader: CSSLoader,
};
