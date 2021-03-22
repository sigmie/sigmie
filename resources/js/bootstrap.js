import Vue from 'vue'

import Vuelidate from 'vuelidate'
import Echo from 'laravel-echo'
import axios from 'axios'
import route from 'ziggy'
import { plugin } from '@inertiajs/inertia-vue'
import Pusher from 'pusher-js'
import { Ziggy } from './routes'
import Clipboard from 'v-clipboard'
import vueHeadful from 'vue-headful'
import { mixin as clickaway } from 'vue-clickaway2'
import { InertiaProgress } from '@inertiajs/progress'

window.Pusher = Pusher

Vue.prototype.$http = axios.create({
  baseURL: '/',
  timeout: 5000,
  withCredentials: true,
  xsrfCookieName: 'XSRF-TOKEN', // default
  xsrfHeaderName: 'X-XSRF-TOKEN' // default
})

InertiaProgress.init({
  delay: 250,
  color: '#ff826c',
  includeCSS: true,
  showSpinner: false
})

Vue.mixin(clickaway)
Vue.use(plugin)
Vue.use(Vuelidate)
Vue.use(Clipboard)
Vue.prototype.$route = (name, params, absolute) => route(name, params, absolute, Ziggy)

Vue.prototype.$socket = new Echo({
  broadcaster: 'pusher',
  key: process.env.MIX_PUSHER_APP_KEY,
  cluster: process.env.MIX_PUSHER_APP_CLUSTER
})

Vue.component('vue-headful', vueHeadful)
Vue.component('stripe', require('./essentials/stripe').default)
Vue.component('paddle', require('./essentials/paddle').default)
Vue.component('form-input', require('./ui/forms/input').default)
Vue.component('form-datetime', require('./ui/forms/datetime').default)
Vue.component('form-slider', require('./ui/forms/slider').default)
Vue.component('form-checkbox', require('./ui/forms/checkbox').default)
Vue.component('form-textarea', require('./ui/forms/textarea').default)
Vue.component('form-select', require('./ui/forms/select').default)
Vue.component('button-primary', require('./ui/buttons/primary').default)
Vue.component('button-secondary', require('./ui/buttons/secondary').default)
Vue.component('button-github', require('./ui/buttons/github').default)
Vue.component('button-disabled', require('./ui/buttons/disabled').default)
Vue.component('button-danger', require('./ui/buttons/danger').default)
Vue.component('logo-white', require('./ui/logos/white').default)
Vue.component('logo-default', require('./ui/logos/default').default)
Vue.component('alert-danger', require('./ui/alerts/danger').default)
Vue.component('alert-success', require('./ui/alerts/success').default)
Vue.component('alert-info', require('./ui/alerts/info').default)
Vue.component('modal', require('./ui/essentials/modal').default)
Vue.component('bar', require('./ui/essentials/bar').default)
Vue.component('spinner', require('./ui/essentials/spinner').default)
Vue.component('loader', require('./ui/essentials/loader').default)
Vue.component('link-default', require('./ui/link/default').default)
Vue.component('icon-sigmie', require('./ui/icons/sigmie').default)
Vue.component('icon-link', require('./ui/icons/link').default)
Vue.component('icon-globe', require('./ui/icons/globe').default)
Vue.component('icon-x', require('./ui/icons/x').default)
Vue.component('icon-chat', require('./ui/icons/chat').default)
Vue.component('icon-server', require('./ui/icons/server').default)
Vue.component('icon-notification', require('./ui/icons/notification').default)
Vue.component('icon-refresh', require('./ui/icons/refresh').default)
Vue.component('icon-cheveron-right', require('./ui/icons/cheveron/right').default)
Vue.component('icon-cheveron-down', require('./ui/icons/cheveron/down').default)
Vue.component('icon-cheveron-left', require('./ui/icons/cheveron/left').default)
Vue.component('icon-arrow-right', require('./ui/icons/arrow/right').default)
Vue.component('icon-check', require('./ui/icons/check').default)
Vue.component('icon-duplicate', require('./ui/icons/duplicate').default)
Vue.component('icon-check-circle', require('./ui/icons/check-circle').default)
Vue.component('icon-home', require('./ui/icons/home').default)
Vue.component('icon-team', require('./ui/icons/team').default)
Vue.component('icon-folder', require('./ui/icons/folder').default)
Vue.component('icon-calendar', require('./ui/icons/calendar').default)
Vue.component('icon-inbox', require('./ui/icons/inbox').default)
Vue.component('icon-report', require('./ui/icons/report').default)
Vue.component('icon-bell', require('./ui/icons/bell').default)
Vue.component('icon-key', require('./ui/icons/key').default)
Vue.component('icon-puzzle', require('./ui/icons/puzzle').default)
Vue.component('icon-desktop', require('./ui/icons/desktop').default)
Vue.component('icon-danger', require('./ui/icons/danger').default)
Vue.component('icon-info', require('./ui/icons/info').default)
Vue.component('icon-cog', require('./ui/icons/cog').default)
Vue.component('icon-heart', require('./ui/icons/heart').default)
Vue.component('icon-list', require('./ui/icons/list').default)
Vue.component('icon-document-add', require('./ui/icons/document/add').default)
Vue.component('icon-plus', require('./ui/icons/plus').default)
Vue.component('icon-edit', require('./ui/icons/edit').default)
Vue.component('icon-trash', require('./ui/icons/trash').default)
