import { InertiaApp } from '@inertiajs/inertia-vue'

export const app = document.getElementById('app')

export default {
  components: {
    sidebar: require('./views/common/sidebar/sidebar').default,
    navbar: require('./views/common/navbar/navbar').default
  },
  render: h => h(InertiaApp, {
    props: {
      initialPage: JSON.parse(app.dataset.page),
      resolveComponent: name => require(`./views/${name}`).default
    }
  }),
  mounted () {
  },
  data () {
    return {
    }
  }
}
