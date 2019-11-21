const path = require('path')
const globImporter = require('node-sass-glob-importer')
const _StyleLintPlugin = require('stylelint-webpack-plugin')

module.exports = async ({ config }) => {
  // Transpile Gatsby module because Gatsby includes un-transpiled ES6 code.
  config.module.rules[0].exclude = [/node_modules\/(?!(gatsby)\/)/]

   // use installed babel-loader which is v8.0-beta (which is meant to work with @babel/core@7)
  config.module.rules[0].use[0].loader = require.resolve("babel-loader")

   // use @babel/preset-react for JSX and env (instead of staged presets)
  config.module.rules[0].use[0].options.presets = [
    require.resolve("@babel/preset-react"),
    require.resolve("@babel/preset-env"),
  ]

   config.module.rules[0].use[0].options.plugins = [
    // use @babel/plugin-proposal-class-properties for class arrow functions
    require.resolve("@babel/plugin-proposal-class-properties"),
    // use babel-plugin-remove-graphql-queries to remove static queries from components when rendering in storybook
    require.resolve("babel-plugin-remove-graphql-queries"),
  ]

   // Prefer Gatsby ES6 entrypoint (module) over commonjs (main) entrypoint
  config.resolve.mainFields = ["browser", "module", "main"]

  // Twig
  config.module.rules.push({
    test: /\.twig$/,
    use: [
      {
        loader: 'twig-loader',
        options: {
          twigOptions: {
            namespaces: {
              atoms: path.resolve(__dirname, '../', 'components/01-atoms'),
              molecules: path.resolve(__dirname, '../', 'components/02-molecules'),
              organisms: path.resolve(__dirname, '../', 'components/03-organisms'),
              templates: path.resolve(__dirname, '../', 'components/04-templates'),
            },
          },
        },
      },
    ],
  });

  // SCSS
  config.module.rules.push({
    test: /\.s[ac]ss$/i,
    use: [
      'style-loader',
      {
        loader: 'css-loader',
        options: {
          sourceMap: true,
        },
      },
      {
        loader: 'sass-loader',
        options: {
          sourceMap: true,
          sassOptions: {
            importer: globImporter()
          }
        },
      },
    ],
  });

  config.plugins.push(
    new _StyleLintPlugin({
      configFile: path.resolve(__dirname, '../', 'webpack/.stylelintrc'),
      context: path.resolve(__dirname, '../', 'components'),
      files: '**/*.scss',
      failOnError: false,
      quiet: false,
    })
  )

  // YAML
  config.module.rules.push({
    test: /\.ya?ml$/,
    loader: 'js-yaml-loader',
  })

  // JS
  config.module.rules.push({
    test: /\.js$/,
    exclude: /node_modules/,
    loader: 'eslint-loader',
    options: {
      cache: true,
    },
  })

  return config
}
