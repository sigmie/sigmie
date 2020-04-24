<template>
  <div
    class="mx-auto bg-gray-50 sm:rounded-lg flex max-w-4xl"
    v-if="projects.length === 0 && state == 'fetched'"
  >
    <div class="bg-white shadow sm:rounded-lg max-w-md flex-1 mr-2">
      <div class="px-4 py-5 sm:p-6">
        <h3 class="text-lg leading-6 font-medium text-gray-900 text-right">Create new cluster</h3>
        <div class="mt-2 sm:flex sm:items-start sm:justify-between">
          <div class="mt-5 sm:mt-0 sm:ml-6 sm:flex-shrink-0 sm:flex sm:items-center">
            <span class="inline-flex rounded-md shadow-sm">
              <button-primary text="Create" type="button"></button-primary>
            </span>
          </div>
          <div class="max-w-xl text-sm leading-5 text-gray-500 text-right">
            <p>
              Let us create an Elasticsearch cluster
              <br />on your Cloud platform.
            </p>
          </div>
        </div>
      </div>
    </div>
    <div class="bg-white shadow sm:rounded-lg max-w-md flex-1 ml-2">
      <div class="px-4 py-5 sm:p-6">
        <h3 class="text-lg leading-6 font-medium text-gray-900">Connect existing cluster</h3>
        <div class="mt-2 sm:flex sm:items-start sm:justify-between">
          <div class="max-w-xl text-sm leading-5 text-gray-500">
            <p>Connect your existing Elasticsearch cluster.</p>
          </div>
          <div class="mt-5 sm:mt-0 sm:ml-6 sm:flex-shrink-0 sm:flex sm:items-center">
            <span class="inline-flex rounded-md shadow-sm">
              <button-primary text="Connect" type="button"></button-primary>
            </span>
          </div>
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
    // let response = await this.$http.get("/project");

    this.state = "fetched";

    this.projects = response.data;
  }
});

export default component;
</script>

<style>
</style>
