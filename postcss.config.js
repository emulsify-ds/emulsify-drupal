// postcssCustomProperties only needed for IE11 - remove if unnecessary for your project.
const postcssCustomProperties = require('postcss-custom-properties');
const autoPrefixer = require('autoprefixer');

module.exports = {
  plugins: [postcssCustomProperties(), autoPrefixer()],
};
