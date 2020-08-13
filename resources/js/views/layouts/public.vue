<template>
  <div class="min-h-full relative pb-20">
    <vue-headful :title="title + ' | Sigmie'" />

    <main class="mx-auto container m-0 w-256 max-w-full">
      <slot></slot>
    </main>

    <footer class="bottom-0 absolute text-cool-gray-400 text-sm w-full h-20">
      <legal-footer class="px-7 space-x-8 mx-auto w-4/5 justify-center flex-wrap flex pb-3"></legal-footer>
    </footer>
  </div>
</template>

<script>
export default {
  props: ["title"],
  components: {
    legalFooter: require("../common/legal/footer").default,
  },
};
</script>

<style scoped>
</style>
