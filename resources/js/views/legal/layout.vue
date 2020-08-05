<template>
  <div class="antialiased text-gray-900">
    <div class="grid col-gap-4 row-gap-8 grid-cols-3">
      <div class="col-span-1">
        <inertia-link class="float-right pt-24 pr-10" :href="$route('landing')">
          <icon-cheveron-left class="text-gray-300 h-8 mb-24"></icon-cheveron-left>
        </inertia-link>
      </div>
      <div class="md:col-span-1 pb-10">
        <div class="max-w-3xl mx-auto lg:max-w-4xl xl:max-w-6xl">
          <article class="mt-10 md:mt-20">
            <div class="space-y-10 sm:space-y-12 lg:space-y-20 xl:space-y-24">
              <div class="prose prose-sm sm:prose lg:prose-lg xl:prose-2xl mx-auto">
                <slot></slot>
              </div>
            </div>
          </article>
        </div>
      </div>
      <div class="col-span-1">
        <div class="pl-10 pt-24 w-72">
          <h3
            class="px-3 text-xs leading-4 font-semibold text-gray-500 uppercase tracking-wider"
          >Contents</h3>
          <div class="mt-1" role="group" aria-labelledby="projects-headline">
            <inertia-link
              :href="$route('legal.about')"
              :class="[ isRoute('legal.about') ? 'bg-gray-200': '']"
              class="group flex items-center px-3 py-2 text-sm leading-5 font-medium text-gray-600 rounded-md hover:text-gray-900 focus:outline-none focus:text-gray-900 transition ease-in-out duration-150"
            >About Us</inertia-link>
            <inertia-link
              :class="[ isRoute('legal.terms') ? 'bg-gray-200': '']"
              class="group flex items-center px-3 py-2 text-sm leading-5 font-medium text-gray-600 rounded-md hover:text-gray-900 focus:outline-none focus:text-gray-900 transition ease-in-out duration-150"
              :href="$route('legal.terms')"
            >Terms of Service</inertia-link>
            <inertia-link
              :class="[ isRoute('legal.privacy') ? 'bg-gray-200': '']"
              class="group flex items-center px-3 py-2 text-sm leading-5 font-medium text-gray-600 rounded-md hover:text-gray-900 focus:outline-none focus:text-gray-900 transition ease-in-out duration-150"
              :href="$route('legal.privacy')"
            >Privacy Policy</inertia-link>
            <inertia-link
              :class="[ isRoute('legal.imprint') ? 'bg-gray-200': '']"
              class="group flex items-center px-3 py-2 text-sm leading-5 font-medium text-gray-600 rounded-md hover:text-gray-900 focus:outline-none focus:text-gray-900 transition ease-in-out duration-150"
              :href="$route('legal.imprint')"
            >Imprint</inertia-link>
            <inertia-link
              :class="[ isRoute('legal.disclaimer') ? 'bg-gray-200': '']"
              class="group flex items-center px-3 py-2 text-sm leading-5 font-medium text-gray-600 rounded-md hover:text-gray-900 focus:outline-none focus:text-gray-900 transition ease-in-out duration-150"
              :href="$route('legal.disclaimer')"
            >Disclaimer</inertia-link>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  components: {
    // sidebar: require("../common/sidebar/sidebar").default,
  },
  props: [],
  methods: {
    isRoute(name) {
      return this.$route().current(name);
    },
  },
};
</script>

<style scoped>
</style>