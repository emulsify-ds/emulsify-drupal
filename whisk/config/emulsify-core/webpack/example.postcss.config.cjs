// Change the name of this file to postcss.config.js
// for Emulsify Core to recognize and fully override
// its provided configuration.
const autoprefixer    = require('autoprefixer');

module.exports = {
  plugins: [
    autoprefixer(),
  ],
};
