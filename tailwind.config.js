module.exports = {
  plugins: [
    require('@tailwindcss/ui'),
    require('@tailwindcss/typography')
  ],
  purge: [
    './resources/js/**/*.vue',
    './resources/views/**/*.blade.php'
  ],
  variants: {
    borderWidth: ['first', 'responsive']
  },
  theme: {
    extend: {
      colors: {
        // orange
        'theme-primary': '#FF6347',
        // purple
        'theme-secondary': '#403C56',
        'theme-secondary-lighter': '#2f2f41',
        // gray
        'theme-tertiary': '#B8C4D1'
      },
      spacing: {
        72: '18rem',
        84: '21rem',
        96: '24rem',
        128: '32rem',
        256: '64rem'
      }
    }
  }
}
