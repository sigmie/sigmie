<template>
  <layout title="Sign Up">
    <div class="flex content-center justify-center h-128">
      <div class="self-center">
        <spinner class="mx-auto block" color="cool-gray-400"></spinner>
        <p
          class="p-6 text-cool-gray-500 text-center"
        >Awaiting authorization from our payment provider...</p>
      </div>
    </div>
  </layout>
</template>

<script>
import Layout from "../../layouts/public";
export default {
  components: {
    Layout,
  },
};
</script>

<style scoped>
</style>