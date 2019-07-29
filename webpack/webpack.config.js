const path = require('path');
const loaders = require('./loaders');
const plugins = require('./plugins');

module.exports = {
  optimization: {
    splitChunks: {
      cacheGroups: {
        styles: {
          name: 'styles',
          test: /\.css$/,
          chunks: 'all',
          enforce: true,
        },
      },
    },
    runtimeChunk: true,
  },
  entry: ["./app.js"],
  module: {
    rules: [
      loaders.JSLoader,
      loaders.CSSLoader
    ]
  },
  plugins: [
    plugins.StyleLintPlugin,
    plugins.MiniCssExtractPlugin,
  ],
  output: {
    path: path.resolve("./dist"),
    filename: "js/[name].bundle.js"
  },
};
