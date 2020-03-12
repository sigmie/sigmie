import Vue from 'vue'

import './bootstrap'

import $root from './root'

const vm = new Vue($root)

vm.$mount('#app')
