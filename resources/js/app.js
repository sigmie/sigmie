import Vue from 'vue'
import bootstrap from './bootstrap'

import $root, { app } from './root'

async function init () {
  await bootstrap()

  const vm = new Vue($root)

  vm.$mount(app)
}

init()
