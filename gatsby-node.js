// const StyleLintPlugin = require('stylelint-webpack-plugin');
// const ImageminPlugin = require("imagemin-webpack");
const path = require('path');

exports.onCreateWebpackConfig = ({
  actions,
}) => {
  actions.setWebpackConfig({
    module: {
      rules: [
        {
          include: path.resolve(__dirname, 'components/style.css'),
          use: [
            'style-loader',
            { loader: 'css-loader', options: { importLoaders: 1 } },
            'postcss-loader'
          ]
        },
      ]
    },
    output: {
      filename: 'style.css',
      path: path.resolve(__dirname, 'dist'),
    }
    // plugins: [
    //   new StyleLintPlugin(options),
    //   // Make sure that the plugin is after any plugins that add images, example `CopyWebpackPlugin`
    //   new ImageminPlugin({
    //     bail: false, // Ignore errors on corrupted images
    //     cache: true,
    //     imageminOptions: { 
    //       // Lossless optimization with custom option
    //       // Feel free to experiment with options for better result for you
    //       plugins: [
    //         ["gifsicle", { interlaced: true }],
    //         ["jpegtran", { progressive: true }],
    //         ["optipng", { optimizationLevel: 5 }],
    //         [
    //           "svgo",
    //           {
    //             plugins: [
    //               {
    //                 removeViewBox: false
    //               }
    //             ]
    //           }
    //         ]
    //       ]
    //     }
    //   })
    // ],
  })
}
