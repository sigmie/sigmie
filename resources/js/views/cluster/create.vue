<template>
  <div>
    <div class="md:flex md:items-center md:justify-between">
      <div class="flex-1 min-w-0">
        <h2
          class="text-lg font-bold leading-7 text-gray-900 sm:text-3xl sm:leading-9 sm:truncate"
        >Create a new cluster</h2>
      </div>
      <div class="mt-4 flex md:mt-0 md:ml-4">
        <span class="ml-3 shadow-sm rounded-md">
          <button
            type="button"
            class="inline-flex items-center px-4 py-2 border border-transparent text-sm leading-5 font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:shadow-outline-indigo focus:border-indigo-700 active:bg-indigo-700 transition duration-150 ease-in-out"
          >Use existing</button>
        </span>
      </div>
    </div>

    <general></general>

    <separator></separator>

  </div>
</template>

<script>
export default {
  components: {
    general: require("./_partials/create/general").default,
    separator: require("./_partials/create/separator").default
  }
};
</script>

<style>
</style>
