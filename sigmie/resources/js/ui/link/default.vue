<template>
  <a
    class="group inline-flex items-center font-medium text-theme-orange-light-800 hover:text-theme-orange-light-600"
  >
    <component v-if="icon" class="mr-1 h-4 w-4" :is="'icon-' + icon"></component>
    <span class="ml-2"> Copy link </span>
  </a>
</template>

<script>
export default {
  props: ["icon"],
};
</script>

<style scoped>
</style>
