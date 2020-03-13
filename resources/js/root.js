import VueRouter from 'vue-router'
import routes from './routes'

export default {
  components: {
    sidebar: require('./views/common/sidebar').default,
    navbar: require('./views/common/navbar').default
  },
  router: new VueRouter({
    routes,
    mode: 'history',
    base: '/'
  }),
  mounted () {
    if (typeof this.$refs.bar === 'undefined') {
      return
    }

    this.$refs.bar.animate(0.7)
  },
  data () {
    return {
    }
  }
}
