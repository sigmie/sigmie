<template>
  <layout :title="title">
    <div class="min-h-full relative pb-20">
      <main class="mx-auto container m-0 w-256 max-w-full">
        <slot></slot>
      </main>

      <footer class="bottom-0 absolute text-cool-gray-400 text-sm w-full h-20 pt-5">
        <legal-footer class="md:px-7 md:space-x-8 mx-auto w-full justify-center flex-wrap flex"></legal-footer>
      </footer>
    </div>
  </layout>
</template>

<script>
import Layout from "./layout";

export default {
  props: ["title"],
  components: {
    legalFooter: require("../common/legal/footer").default,
    Layout,
  },
};
</script>

<style scoped>
</style>
