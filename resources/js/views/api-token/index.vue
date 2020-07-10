<template>
  <app>
    <div class="flex md:items-center md:justify-between md:mb-4">
      <div class="flex-1 min-w-0">
        <h2
          class="text-lg font-bold leading-7 text-gray-900 sm:text-3xl sm:leading-9 sm:truncate"
        >API Tokens</h2>
      </div>
      <div class="flex-1 md:mt-0 md:ml-4">
        <a
          class="tracking-wide text-sm text-gray-400 float-right cursor-not-allowed"
          href="#"
        >Use existing</a>
      </div>
    </div>
    <div class="flex flex-col">
      <div class="-my-2 py-2 overflow-x-auto sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
        <div
          class="align-middle inline-block min-w-full shadow overflow-hidden sm:rounded-lg border-b border-gray-200"
        >
          <table class="min-w-full">
            <thead>
              <tr>
                <th
                  class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider"
                >Name</th>
                <th
                  class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider"
                >Environment</th>
                <th
                  class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider"
                >Environment</th>
                <th
                  class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider"
                >Token</th>
                <th class="px-6 py-3 border-b border-gray-200 bg-gray-50"></th>
              </tr>
            </thead>
            <tbody class="bg-white">
              <tr>
                <td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200">
                  <div class="flex items-center">
                    <div class>
                      <div class="text-sm leading-5 font-medium text-gray-900">Admin</div>
                      <div class="text-sm leading-5 text-gray-500">
                        This is the API key
                        <b>only</b> in your backend.
                      </div>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200">
                  <div class="text-sm leading-5 text-gray-500">Production</div>
                </td>
                <td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200">
                  <span
                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800"
                  >Active</span>
                </td>
                <td
                  class="px-6 py-4 whitespace-no-wrap border-b border-gray-200 text-sm leading-5 text-gray-500"
                >**********************</td>
                <td
                  class="px-6 py-4 whitespace-no-wrap text-right border-b border-gray-200 text-sm leading-5 font-medium"
                >
                  <a href="#" class="text-indigo-600 hover:text-indigo-900">Copy</a>&nbsp;|&nbsp;
                  <a href="#" class="text-indigo-600 hover:text-indigo-900">Regenerate</a>
                </td>
              </tr>
              <tr>
                <td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200">
                  <div class="flex items-center">
                    <div class>
                      <div class="text-sm leading-5 font-medium text-gray-900">Search-Only</div>
                      <div
                        class="text-sm leading-5 text-gray-500"
                      >This is the public API key to use in your frontend code.</div>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200">
                  <div class="text-sm leading-5 text-gray-500">Production</div>
                </td>
                <td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200">
                  <span
                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800"
                  >Inactive</span>
                </td>
                <td
                  class="px-6 py-4 whitespace-no-wrap border-b border-gray-200 text-sm leading-5 text-gray-500"
                >**********************</td>
                <td
                  class="px-6 py-4 whitespace-no-wrap text-right border-b border-gray-200 text-sm leading-5 font-medium"
                >
                  <a href="#" class="text-indigo-600 hover:text-indigo-900">Copy</a>&nbsp;|&nbsp;
                  <a href="#" class="text-indigo-600 hover:text-indigo-900">Regenerate</a>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </app>
</template>

<script>
import App from "../layouts/app";

export default {
  components: {
    App
  },
  data() {
    return {};
  },
  methods: {}
};
</script>

<style>
</style>
