import { InertiaApp } from '@inertiajs/inertia-vue'

export const app = document.getElementById('app')

export default {
  render: h => h(InertiaApp, {
    props: {
      initialPage: JSON.parse(app.dataset.page),
      resolveComponent: name => require(`./views/${name}`).default
    }
  })
}
