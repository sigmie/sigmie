import VueRouter from 'vue-router'
import routes from './routes'

export default {
    components: {
        sidebar: require('./views/common/sidebar').default,
        navbar: require('./views/common/navbar').default
    },
    router: new VueRouter({
        routes,
        mode: 'history',
        base: '/'
    }),
    data() {
        return {
        }
    },
    mounted() {
        this.$router.beforeEach((to, from, next) => {
            this.$root.$refs.bar.animate(0.4, [], next)
        });

        this.$router.afterEach(() => {
            this.$root.$refs.bar.animate(0.7)
        });
    },
}
