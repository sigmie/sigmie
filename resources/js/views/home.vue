<template>
  <div>
    <div class="bg-gray-50 sm:rounded-lg" v-if="projects.length === 0 && state == 'fetched'">
      <div class="px-4 py-5 sm:p-6">
        <h3 class="text-lg leading-6 font-medium text-gray-900">No projects yet</h3>
        <div class="mt-2 max-w-xl text-sm leading-5 text-gray-500">
          <p>Create new Project</p>
        </div>
        <div class="mt-5">
          <span class="inline-flex rounded-md shadow-sm">
            <button
              type="button"
              class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm leading-5 font-medium rounded-md text-gray-700 bg-white hover:text-gray-500 focus:outline-none focus:border-blue-300 focus:shadow-outline-blue active:text-gray-800 active:bg-gray-50 transition ease-in-out duration-150"
            >Contact sales</button>
          </span>
        </div>
      </div>
    </div>
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
    let response = await this.$http.get("/project");

    this.state = "fetched";

    this.projects = response.data;
  }
});

export default component;
</script>

<style>
</style>
