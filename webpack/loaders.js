const path = require('path');
const MiniCssExtractPlugin = require("mini-css-extract-plugin");

const JSLoader = {
  test: /^(?!.*\.component\.js$)(?!.*\.stories\.js$).*\.js$/,
  include: [
    path.resolve(__dirname, "components")
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

const FileLoader = {
  test: /\.(gif|png|jpe?g|svg)$/i,
  include: [
    path.resolve(__dirname, "images")
  ],
  use: [
    'file-loader',
    {
      loader: 'image-webpack-loader',
      options: {
        disable: true, // webpack@2.x and newer
      },
    },
  ],
};

module.exports = {
  JSLoader: JSLoader,
  CSSLoader: CSSLoader,
  FileLoader: FileLoader,
};
