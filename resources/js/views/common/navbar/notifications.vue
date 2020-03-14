<template>
  <ul v-cloak>
    <li v-for="notification in notifications" class="border-t border-gray-200 first:border-t-0">
      <a
        href="#"
        class="block bg-gray-100 hover:bg-gray-50 focus:outline-none focus:bg-gray-50 transition duration-150 ease-in-out"
      >
        <div class="px-4 py-4 sm:px-6">
          <div class="flex items-center justify-between">
            <div class="text-sm leading-5 font-medium text-orange-600 truncate">Removed</div>
            <div class="ml-2 flex-shrink-0 flex">
              <span
                class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-orange-100 text-orange-700"
              >#go-james</span>
            </div>
          </div>
          <div class="mt-2 sm:flex sm:justify-between">
            <div class="sm:flex">
              <div
                class="mr-6 flex items-center text-sm leading-5 text-gray-500"
              >Lorem, ipsum dolor sit amet cons.</div>
            </div>
            <div class="mt-2 flex items-center text-sm leading-5 text-gray-400 sm:mt-0">
              <span class="w-12 text-xs">
                <time datetime="2020-01-07">3 days</time>
              </span>
            </div>
          </div>
        </div>
      </a>
    </li>
  </ul>
</template>

<script>
export default {
  props: {
      notifications:{
          default: []
      }
  }
};
</script>

<style>
</style>
