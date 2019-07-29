module.exports = {
  plugins: [
    {
      resolve: "gatsby-theme-emulsify",
      options: {
        componentLibPath: 'components',
        docPagesPath: 'styleguide',
        basePath: __dirname
      },
    },
  ],
}
