export default [
  {
    path: '/register',
    name: 'register-view',
    component: require('./views/auth/register').default
  },
  {
    path: '/login',
    name: 'login-view',
    component: require('./views/auth/login').default
  }
]
