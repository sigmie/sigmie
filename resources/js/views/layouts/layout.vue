<template>
  <div class="min-h-full h-full">
    <vue-headful :title="title + ' | Sigmie'" />
    <slot />
  </div>
</template>

<script>
export default {
  props: ["title"],
};
</script>

<style scoped>
</style>
