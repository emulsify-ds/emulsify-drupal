const path = require('path');
const glob = require('glob');
const loaders = require('./loaders');
const plugins = require('./plugins');

const webpackDir = path.resolve(__dirname);
const rootDir = path.resolve(__dirname, '..');
const distDir = path.resolve(rootDir, 'dist');
const componentJs = glob.sync(
  `${path.resolve(rootDir, 'components')}/**/*.js`,
  { ignore: ['**/*.stories.js', '**/*.component.js'] }
);

module.exports = [
  {
    entry: componentJs.reduce(
      (acc, cur) => ({
        ...acc,
        [path.basename(cur)]: cur
      }),
      {}
    ),
    optimization: {
      runtimeChunk: true
    },
    module: {
      rules: [loaders.JSLoader]
    },
    output: {
      path: distDir,
      filename: 'js/[name]'
    }
  },
  {
    optimization: {
      splitChunks: {
        cacheGroups: {
          styles: {
            name: 'styles',
            test: /\.css$/,
            chunks: 'all',
            enforce: true
          }
        }
      },
      runtimeChunk: true
    },
    entry: {
      svgSprite: path.resolve(webpackDir, 'svgSprite.js'),
      css: path.resolve(webpackDir, 'css.js')
    },
    module: {
      rules: [loaders.CSSLoader, loaders.SVGSpriteLoader]
    },
    plugins: [
      plugins.StyleLintPlugin,
      plugins.MiniCssExtractPlugin,
      plugins.ImageminPlugin,
      plugins.SpriteLoaderPlugin
    ],
    output: {
      path: distDir,
      filename: 'remove/[name].js'
    }
  }
];
