export default [

  {
    path: '/dashboard',
    name: 'dashboard-view',
    component: require('./views/dashboard').default
  },
  {
    path: '/cluster/create',
    name: 'cluster-create-view',
    component: require('./views/cluster/create').default
  },
  {
    path: '/bar',
    name: 'bar-view',
    component: require('./views/bar').default
  }
]
