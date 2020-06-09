module.exports = {
  plugins: [
    require('@tailwindcss/ui')
  ],
  variants: {
    borderWidth: ['first']
  },
  theme: {
    extend: {
      colors: {
        // orange
        'theme-primary': '#FF6347',
        // purple
        'theme-secondary': '#403C56',
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
