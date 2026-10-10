const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const TerserPlugin = require('terser-webpack-plugin');

const config = {
  // Entry points. The keys replace the [name] portion of the output config below.
  entry: {
    admin: [
      './assets/javascript/admin/index.js',
      './assets/styles/admin/index.css'
    ],
    editor: './assets/javascript/editor/index.js'
  },

  // Output files: one for each of our entry points.
  output: {
    // [name] allows for the entry object keys to be used as file names.
    filename: './dist/javascript/[name].js',
    // The path to the JS files.
    path: path.resolve(__dirname, '../assets')
  },

  // Setup a loader to transpile down the latest and great JavaScript so older browsers can understand it.
  module: {
    rules: [
      {
        // Look for any .js files.
        test: /\.js$/,
        // Exclude the node_modules folder.
        exclude: /node_modules/,
        // Use babel loader to transpile the JS files.
        loader: 'babel-loader'
      },
      {
        test: /\.css$/,
        use: [
          MiniCssExtractPlugin.loader,
          'css-loader'
        ]
      }
    ]
  },

  // Keep the @wordpress/i18n function names: translate.wordpress.org finds the
  // strings in the built files by looking for these calls.
  optimization: {
    minimizer: [
      new TerserPlugin({
        extractComments: false,
        terserOptions: {
          mangle: { reserved: ['__', '_n', '_x', '_nx'] }
        }
      })
    ]
  },

  plugins: [
    new MiniCssExtractPlugin({
      filename: './dist/styles/[name].css'
    })
  ]
}

module.exports = config;

