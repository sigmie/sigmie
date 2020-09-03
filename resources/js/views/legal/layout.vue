<template>
  <div class="antialiased text-gray-900">
    <vue-headful :title="title + ' | Sigmie'" />
    <div class="flex flex-col-reverse md:flex-row flex-wrap-reverse">
      <div class="flex-1 md:border-none border-t-2 border-gray-200 mx-10 md:mx-0 py-10 md:pt-24">
        <a
          v-if="hasHistory() === false"
          class="md:float-right mx-auto w-8 block md:mr-15"
          :href="$route('landing')"
        >
          <icon-cheveron-left class="text-gray-300 md:mb-24"></icon-cheveron-left>
        </a>

        <a v-else class="md:float-right mx-auto w-8 block md:mr-15 cursor-pointer" @click.prevent="back">
          <icon-cheveron-left class="text-gray-300 md:mb-24"></icon-cheveron-left>
        </a>
      </div>
      <div class="flex-1 pb-10 px-10 sm:px-0">
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
      <div class="flex-1 px-10 md:px-0">
        <div
          class="md:pl-10 py-10 md:border-none border-b-2 border-gray-200 md:pt-24 md:w-72 mx-auto"
        >
          <h3
            class="px-3 text-xs leading-4 font-semibold text-gray-500 uppercase tracking-wider"
          >Contents</h3>
          <div class="mt-1" role="group">
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
              :class="[ isRoute('legal.disclaimer') ? 'bg-gray-200': '']"
              class="group flex items-center px-3 py-2 text-sm leading-5 font-medium text-gray-600 rounded-md hover:text-gray-900 focus:outline-none focus:text-gray-900 transition ease-in-out duration-150"
              :href="$route('legal.disclaimer')"
            >Disclaimer</inertia-link>
            <inertia-link
              :class="[ isRoute('legal.imprint') ? 'bg-gray-200': '']"
              class="group flex items-center px-3 py-2 text-sm leading-5 font-medium text-gray-600 rounded-md hover:text-gray-900 focus:outline-none focus:text-gray-900 transition ease-in-out duration-150"
              :href="$route('legal.imprint')"
            >Imprint</inertia-link>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  props: ["title"],
  methods: {
    isRoute(name) {
      return this.$route().current(name);
    },
    hasHistory() {
      return window.history.length > 2;
    },
    back(){
  window.history.back();
    }
  },
};
</script>

<style scoped>
</style>