module.exports = {
  plugins: [
    require('@tailwindcss/ui')
  ],
  theme: {
    extend: {
      colors: {
        'theme-primary': '#3F3D54',
        'theme-grey': '#A8A8A8',
        'theme-orange-500': '#FF6347',
        'theme-orange-600': '#fb6c52'
      },
      spacing: {
        72: '18rem',
        84: '21rem',
        96: '24rem',
        128: '32rem',
        256: '64rem'
      }
    }
  },
  variants: {}
}
