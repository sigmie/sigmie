import Vue from 'vue';
import axios from 'axios';
import Routes from './routes';
import VueRouter from 'vue-router';

let token = document.head.querySelector('meta[name="csrf-token"]');

if (token) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
}

Vue.use(VueRouter);

Vue.component('csrf-token', require('./common/csrf').default);
Vue.component('input-field', require('./components/form/input').default);
Vue.component('primary-button', require('./components/form/button').default);
Vue.component('form-heading', require('./components/form/heading').default);
Vue.component('content-separator', require('./components/form/separator').default);
Vue.component('container-gray', require('./components/container/gray').default);
Vue.component('stripe', require('./components/form/stripe').default);

const router = new VueRouter({
    routes: Routes,
    mode: 'history',
    base: '/',
});

new Vue({
    el: '#app',

    router,

    data() {
        return {
            acme: {
            },
            bar: 'bar',
        }
    },

    mounted() {
        console.log('ho');
    },


    methods: {
        foo() {
            return 'foo'
        }
    }
});
