import VueRouter from 'vue-router'
import { InertiaApp } from '@inertiajs/inertia-vue'
import routes from './routes'

export const app = document.getElementById('app')

export default {
    components: {
        sidebar: require('./views/common/sidebar/sidebar').default,
        navbar: require('./views/common/navbar/navbar').default
    },
    render: h => h(InertiaApp, {
        props: {
            initialPage: JSON.parse(app.dataset.page),
            resolveComponent: name => require(`./views/${name}`).default,
        },
    }),
    router: new VueRouter({
        routes,
        mode: 'history',
        base: '/'
    }),
    mounted() {
        if (typeof this.$refs.bar === 'undefined') {
            return
        }

        this.$refs.bar.animate(0.7)
    },
    data() {
        return {
        }
    }
}
