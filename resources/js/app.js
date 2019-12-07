import Vue from 'vue';
import axios from 'axios';
import Routes from './routes';
import VueRouter from 'vue-router';

let token = document.head.querySelector('meta[name="csrf-token"]');

if (token) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
}

Vue.use(VueRouter);

const router = new VueRouter({
    routes: Routes,
    mode: 'history',
    base: '/',
});

console.log('yo');


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
