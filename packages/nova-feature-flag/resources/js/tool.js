Nova.booting((Vue, router, store) => {
  router.addRoutes([
    {
      name: 'nova-feature-flags',
      path: '/nova-feature-flags',
      component: require('./components/Tool')
    }
  ])
})
