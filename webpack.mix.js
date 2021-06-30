const mix = require('laravel-mix')
const path = require('path')

mix.alias({
  ziggy: path.resolve('vendor/tightenco/ziggy/dist')
})

// hot reload
mix.options({
  hmrOptions: {
    host: '0.0.0.0',
    port: 8081
  }
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
