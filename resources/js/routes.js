export default [
    {
        path: '/home',
        name: 'home-view',
        component: require('./views/home').default
    },
    {
        path: '/bar',
        name: 'bar-view',
        component: require('./views/bar').default
    }
]
