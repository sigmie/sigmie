<template>
  <div
    class="mx-auto max-w-5xl"
    v-if="projects.length === 0 && state == 'fetched'"
  >
  <cluster-wizard></cluster-wizard>
  </div>
</template>

<script>
import View from "./View";

var component = View.extend({
  data() {
    return {
      projects: []
    };
  },
  async beforeMount() {
    // let response = await this.$http.get("/project");

    this.state = "fetched";

    this.projects = response.data;
  }
});

export default component;
</script>

<style>
</style>
