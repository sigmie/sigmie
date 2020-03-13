export default [
  {
    path: '/dashboard',
    name: 'dashboard-view',
    component: require('./views/dashboard').default
  },
  {
    path: '/bar',
    name: 'bar-view',
    component: require('./views/bar').default
  }
]
