<template>
  <div class="min-h-full h-full">
    <vue-headful :title="title + ' | Sigmie'" />
    <slot />
  </div>
</template>

<script>
export default {
  props: ["title"],
  beforeMount() {
    this.$http.defaults.headers["X-CSRF-TOKEN"] = this.$page.csrf_token;
    this.$socket.options.auth.headers["X-CSRF-TOKEN"] =this.$page.csrf_token;
  },
};
</script>

<style scoped>
</style>