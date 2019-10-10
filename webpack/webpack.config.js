const path = require('path');
const glob = require('glob');
const loaders = require('./loaders');
const plugins = require('./plugins');

const webpackDir = path.resolve(__dirname)
const rootDir = path.resolve(__dirname, '..');
const distDir = path.resolve(rootDir, 'dist');
const componentsDir = path.resolve(rootDir, 'components');
const componentJS = glob.sync(`${componentsDir}/**/*.js`, {ignore: ['**/*.stories.js', '**/*.component.js']});

const entry = {};
  
componentJS.forEach((match) => {
  entry[match] = match;
});

const getFilePath = (filePath) => {
  const fileName = path.basename(filePath);
  const relativeDir = path.relative(rootDir, distDir);
  const newFilePath = `${relativeDir}/${fileName}`;
  return newFilePath;
};

module.exports = {
  entry: entry,
  module: {
    rules: [
      loaders.JSLoader,
    ]
  },
  output: {
    path: distDir,
    filename(object) {
      const filePath = object.chunk.name;
      let newFilePath;
  
      if (filePath.indexOf(themewebpackDirPath) !== -1) {
        newFilePath = getFilePath(filePath);
      }
      else {
        newFilePath = getFilePath(filePath);
      }
      return newFilePath;
    },
  },
};

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
  entry: {
    // js: componentJS,
    svgSprite: path.resolve(webpackDir, 'svgSprite.js'),
    css: path.resolve(webpackDir, 'css.js')
  },
  module: {
    rules: [
      loaders.CSSLoader,
      loaders.SVGSpriteLoader,
    ]
  },
  plugins: [
    plugins.StyleLintPlugin,
    plugins.MiniCssExtractPlugin,
    plugins.ImageminPlugin,
    plugins.SpriteLoaderPlugin,
  ],
  output: {
    path: distDir,
    filename: 'remove/[name].js',
  },
};
