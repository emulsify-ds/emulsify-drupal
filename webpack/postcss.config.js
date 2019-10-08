module.exports = {
  plugins: {
    'postcss-import-ext-glob': {},
    'postcss-import': {},
    'postcss-mixins': {}, // Keep before simple-vars and nested
    'postcss-nested': {},
    'postcss-simple-vars': {},
    'autoprefixer': {},
    'cssnano': {},
  }
}
