const mix = require('laravel-mix')
const path = require('path')

mix.alias({
  ziggy: path.resolve('vendor/tightenco/ziggy/dist')
})

// browser sync
mix.browserSync({
  proxy: 'localhost:8080',
  open: false
})

// vue
mix.js('resources/js/app.js', 'public/js').vue()

// tailwind
mix.postCss('resources/css/app.css', 'public/css', [
  require('tailwindcss')
])

mix.extract()
  .version()
  .sourceMaps()
