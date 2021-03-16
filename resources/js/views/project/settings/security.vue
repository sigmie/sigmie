<template>

  <div
    class="py-5 shadow mx-auto bg-white rounded-md sm:overflow-hidden max-w-lg mt-6"
  >
    <div class="">
      <!-- <h3 class="px-6 text-lg leading-6 font-medium text-gray-900">Security</h3> -->

      <p class="px-6 text-md mb-3 leading-6 font-medium text-gray-900">
        Authorized networks
      </p>

      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50 border-t border-b">
          <tr>
            <th
              scope="col"
              class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
            >
              Name
            </th>
            <th
              scope="col"
              class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
            >
              IP
            </th>
            <th
              scope="col"
              class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
            >
              <!-- Actions -->
            </th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <tr>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
              Work
            </td>
            <td
              class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"
            >
              192.185.2.2
            </td>
            <td
              class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"
            >
              <button
                type="button"
                class="bg-white rounded-md font-medium text-theme-orange-light-900 hover:text-theme-orange-light-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500"
              >
                Update
              </button>
              <span class="text-gray-300 px-2" aria-hidden="true">|</span>
              <button
                type="button"
                class="bg-white rounded-md font-medium text-theme-orange-light-900 hover:text-theme-orange-light-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500"
              >
                Remove
              </button>
            </td>
          </tr>

          <tr>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
              Nico home
            </td>
            <td
              class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"
            >
              127.0.0.1
            </td>

            <td
              class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"
            >
              <button
                type="button"
                class="bg-white rounded-md font-medium text-theme-orange-light-900 hover:text-theme-orange-light-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500"
              >
                Update
              </button>
              <span class="text-gray-300 px-2" aria-hidden="true">|</span>
              <button
                type="button"
                class="bg-white rounded-md font-medium text-theme-orange-light-900 hover:text-theme-orange-light-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500"
              >
                Remove
              </button>
            </td>
          </tr>
          <tr>
            <td
              colspan="3"
              class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"
            >
              <button-secondary text="Add IP Address"></button-secondary>
            </td>
          </tr>
        </tbody>
      </table>

      <form
        @submit.prevent="submit"
        class="mt-6 sm:mt-5 space-y-6 sm:space-y-5"
      ></form>
    </div>
  </div>
</template>

<script>
export default {
  props: [],
  data() {
    return {};
  },
  methods: {},
};
</script>

<style scoped>
</style>
