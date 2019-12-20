import Vue from 'vue';
import axios from 'axios';
import Routes from './routes';
import VueRouter from 'vue-router';

let token = document.head.querySelector('meta[name="csrf-token"]');

if (token) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
}

Vue.use(VueRouter);

Vue.component('csrf', require('./essentials/csrf').default);
Vue.component('stripe', require('./essentials/stripe').default);

Vue.component('form-input', require('./ui/forms/input').default);
Vue.component('form-checkbox', require('./ui/forms/checkbox').default);
Vue.component('button-primary', require('./ui/buttons/primary').default);
Vue.component('heading-form', require('./ui/headings/form').default);
Vue.component('heading-card', require('./ui/headings/card').default);
Vue.component('divider-form', require('./ui/dividers/form').default);
Vue.component('divider-sidebar', require('./ui/dividers/sidebar').default);
Vue.component('icon-check', require('./ui/icons/check').default);
Vue.component('card-gray', require('./ui/cards/gray').default);
Vue.component('card-white', require('./ui/cards/white').default);
Vue.component('card-elevated', require('./ui/cards/elevated').default);
Vue.component('logo-white', require('./ui/logos/white').default);

Vue.component('icon-server', require('./ui/icons/server').default);
Vue.component('icon-notification', require('./ui/icons/notification').default);

const router = new VueRouter({
    routes: Routes,
    mode: 'history',
    base: '/',
});

new Vue({
    el: '#app',
    components: {
        navbar: require("./components/common/navbar").default,
        sidebar: require("./components/common/sidebar").default
    },
    router,
    data() {
        return {
            acme: {
            },
            bar: 'bar',
        }
    },
    mounted() {
    },
    methods: {
        foo() {
            return 'foo'
        }
    }
});
