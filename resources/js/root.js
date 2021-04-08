import { InertiaApp } from '@inertiajs/inertia-vue'
import isNull from 'lodash/isNull'
import { debounce } from 'lodash'

export const app = document.getElementById('app')

const render = h => h(InertiaApp, {
  props: {
    initialPage: JSON.parse(app.dataset.page),
    resolveComponent: name => require(`./views/${name}`).default
  }
})

var myMixin = {
  methods: {
    debounce
  }
}

export default {
  render: (isNull(app)) ? () => null : render,
  mixins: [myMixin]
}
