<template>
  <div>
    <vue-headful :title="title + ' | Sigmie'" />
    <slot></slot>
  </div>
</template>

<script>
export default {
  props: ["title"],
  beforeMount() {
    this.$http.defaults.headers["X-CSRF-TOKEN"] = this.$page.csrf_token;
  },
};
</script>

<style scoped>
</style>