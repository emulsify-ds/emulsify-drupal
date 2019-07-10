const path = require("path")

module.exports = {
  plugins: [
    {
      resolve: "gatsby-theme-emulsify",
      options: {
        componentLibPath: 'components',
        docPagesPath: path.join('styleguide', 'pages'),
        basePath: __dirname
      },
    },
  ],
}
