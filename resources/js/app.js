import Vue from 'vue'

import './bootstrap'

import { $root, app } from './root'

const vm = new Vue($root)

vm.$router.beforeEach((to, from, next) => {
    vm.$refs.bar.animate(0.4, [], next)
})

vm.$router.afterEach(() => {
    vm.$refs.bar.animate(0.7)
})

vm.$mount(app)
