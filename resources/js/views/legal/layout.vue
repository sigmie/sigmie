<template>
  <div class="antialiased text-gray-900">
    <div
      class="px-4 py-10 max-w-3xl mx-auto sm:px-6 sm:py-12 lg:max-w-4xl lg:py-16 lg:px-8 xl:max-w-6xl"
    >
      <article class="mt-10 md:mt-20">
        <div class="space-y-10 sm:space-y-12 lg:space-y-20 xl:space-y-24">
          <div class="prose prose-sm sm:prose lg:prose-lg xl:prose-2xl mx-auto">
            <inertia-link :href="$route('landing')">
              <icon-cheveron-left class="text-gray-500 h-8 -ml-16 mb-24"></icon-cheveron-left>
            </inertia-link>
            <slot></slot>
          </div>
        </div>
      </article>
    </div>
  </div>
</template>

<script>
export default {
  components: {
    // sidebar: require("../common/sidebar/sidebar").default,
  },
  props: [],
};
</script>

<style scoped>
</style>