const mix = require('laravel-mix')
const path = require('path')

// ziggy
mix.webpackConfig({
  resolve: {
    alias: {
      ziggy: path.resolve('vendor/tightenco/ziggy/src/js/route.js')
    }
  }
})

// browser sync
mix.browserSync('localhost:8080')

// vue
mix.js('resources/js/app.js', 'public/js').vue()

// tailwind
mix.postCss('resources/css/app.css', 'public/css', [
  require('tailwindcss')
])

mix.extract()
  .version()
  .sourceMaps()
