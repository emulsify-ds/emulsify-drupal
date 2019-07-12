module.exports = {
  parser: 'sugarss',
  plugins: {
    'autoprefixer': {},
    'cssnano': {},
    'postcss-mixins': {}, // Keep before simple-vars and nested
    'postcss-conditionals': {},
    'postcss-import-ext-glob': {},
    'postcss-import': {},
    'postcss-nested': {},
    'postcss-simple-vars': {}
  }
}
