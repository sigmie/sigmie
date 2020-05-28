<template>
  <app>
    <div class="max-w-4xl mx-auto">
      <div class="md:flex md:items-center md:justify-between mb-4">
        <div class="flex-1 min-w-0">
          <h2
            class="text-lg font-bold leading-7 text-gray-900 sm:text-3xl sm:leading-9 sm:truncate"
          >Add new cluster</h2>
        </div>
        <div class="mt-4 flex md:mt-0 md:ml-4">
          <span>
            <a class="tracking-wide text-sm text-gray-400" href>Use existing</a>
          </span>
        </div>
      </div>

      <general></general>

      <separator></separator>

      <provider></provider>

      <separator></separator>

      <search></search>

      <separator></separator>
    </div>
  </app>
</template>

<script>
import App from "../layouts/app";

export default {
  components: {
    App,
    general: require("./_partials/create/general").default,
    provider: require("./_partials/create/provider").default,
    separator: require("./_partials/create/separator").default,
    search: require("./_partials/create/search").default
  }
};
</script>

<style>
</style>
