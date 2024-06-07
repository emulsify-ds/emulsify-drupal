// Change the name of this file to postcss.config.js
// for Emulsify Core to recognize and fully override
// its provided configuration.
const autoPrefixer = require('autoprefixer');

module.exports = {
  plugins: [autoPrefixer()],
};
