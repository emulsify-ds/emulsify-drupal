module.exports = {
  plugins: {
    'autoprefixer': {},
    'postcss-mixins': {}, // Keep before simple-vars and nested
    'postcss-conditionals': {},
    'postcss-import-ext-glob': {},
    'postcss-import': {},
    'postcss-nested': {},
    'postcss-simple-vars': {},
    'cssnano': {},
  }
}
