<template>
  <layout title="Payment" :sidebarDisabled="true">
    <div class="flex content-center justify-center h-128">
      <div class="self-center">
        <spinner class="mx-auto block" color="cool-gray-400"></spinner>
        <p class="p-6 text-cool-gray-500 text-center">{{ text }}</p>
      </div>
      <paddle :vendor="vendor" class="hidden" :paylink="this.paylink"></paddle>
    </div>
  </layout>
</template>

<script>
import Layout from "../layouts/app";

export default {
  components: {
    Layout,
  },
  props: ["paylink", "vendor"],
  data() {
    return {
      text: "Processing...",
    };
  },
};
</script>

<style scoped>
</style>