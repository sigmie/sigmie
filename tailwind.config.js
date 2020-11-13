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
        'theme-orange-light-100': '#ffefed',
        'theme-orange-light-200': '#ffe0da',
        'theme-orange-light-300': '#ffd0c8',
        'theme-orange-light-400': '#ffc1b5',
        'theme-orange-light-500': '#ffb1a3',
        'theme-orange-light-600': '#ffa191',
        'theme-orange-light-700': '#ff927e',
        'theme-orange-light-800': '#ff826c',
        'theme-orange-light-900': '#ff7359',

        'theme-orange-dark-100': '#e65940',
        'theme-orange-dark-200': '#cc4f39',
        'theme-orange-dark-300': '#b34532',
        'theme-orange-dark-400': '#993b2b',
        'theme-orange-dark-500': '#803224',
        'theme-orange-dark-600': '#66281c',
        'theme-orange-dark-700': '#4c1e15',
        'theme-orange-dark-800': '#33140e',
        'theme-orange-dark-900': '#190a07',

        // purple
        'theme-secondary': '#2f2f41',
        'theme-secondary-lighter': '#403C56',

        // gray
        'theme-tertiary': '#B8C4D1',
        'theme-gray-100': '#f8f9fa',
        'theme-gray-200': '#f1f3f6',
        'theme-gray-300': '#eaedf1',
        'theme-gray-400': '#e3e7ed',
        'theme-gray-500': '#dce2e8',
        'theme-gray-600': '#d4dce3',
        'theme-gray-700': '#cdd6df',
        'theme-gray-800': '#c6d0da',
        'theme-gray-900': '#bfcad6'
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
