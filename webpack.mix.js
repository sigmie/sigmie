const mix = require('laravel-mix')
const path = require('path')
const tailwindcss = require('tailwindcss')
const LiveReloadPlugin = require('webpack-livereload-plugin')
require('laravel-mix-purgecss')

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel application. By default, we are compiling the Sass
 | file for the application as well as bundling up all the JS files.
 |
 */

mix.webpackConfig({
  resolve: {
    alias: {
      ziggy: path.resolve('vendor/tightenco/ziggy/src/js/route.js')
    }
  },
  plugins: [
    new LiveReloadPlugin()
  ]
}).js('resources/js/app.js', 'public/js')
  .sass('resources/sass/app.scss', 'public/css')
  .options({
    processCssUrls: false,
    postCss: [tailwindcss('./tailwind.config.js')]
  })
  .extract()
  .version()
  .sourceMaps()
