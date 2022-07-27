const path = require('path');
const glob = require('glob');
const loaders = require('./loaders');
const plugins = require('./plugins');

const webpackDir = path.resolve(__dirname);
const rootDir = path.resolve(__dirname, '..');
const distDir = path.resolve(rootDir, 'dist');

// Glob pattern for scss files that ignore file names prefixed with underscore.
const scssPattern = path.resolve(rootDir, 'components/**/!(_*).scss');
// Glob pattern for JS files.
const jsPattern = path.resolve(
  rootDir,
  'components/**/!(*.stories|*.component|*.min|*.test).js',
);

// Prepare list of scss and js file for "entry".
function getEntries(scssPattern, jsPattern) {
  const entries = {};

  // SCSS entries
  glob.sync(scssPattern).forEach((file) => {
    const filePath = file.split('components/')[1];
    const newfilePath = `css/${filePath.replace('.scss', '')}`;
    entries[newfilePath] = file;
  });

  // JS entries
  glob.sync(jsPattern).forEach((file) => {
    const filePath = file.split('components/')[1];
    const newfilePath = `js/${filePath.replace('.js', '')}`;
    entries[newfilePath] = file;
  });

  entries.svgSprite = path.resolve(webpackDir, 'svgSprite.js');

  // CSS Files.
  glob.sync(`${webpackDir}/css/*js`).forEach((file) => {
    const baseFileName = path.basename(file);
    const newfilePath = `css/${baseFileName.replace('.js', '')}`;
    entries[newfilePath] = file;
  });

  return entries;
}

module.exports = {
  stats: {
    errorDetails: true,
  },
  entry: getEntries(scssPattern, jsPattern),
  module: {
    rules: [
      loaders.CSSLoader,
      loaders.SVGSpriteLoader,
      loaders.ImageLoader,
      loaders.JSLoader,
    ],
  },
  plugins: [
    plugins.MiniCssExtractPlugin,
    plugins.ImageminPlugin,
    plugins.SpriteLoaderPlugin,
    plugins.ProgressPlugin,
    plugins.CleanWebpackPlugin,
  ],
  output: {
    path: distDir,
    filename: '[name].js',
  },
};
