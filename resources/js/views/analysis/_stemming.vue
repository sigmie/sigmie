<template>
  <div>
    <table class="min-w-full divide-y divide-gray-200">
      <thead class="bg-gray-50">
        <tr>
          <th
            scope="col"
            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
          >
            <span class="sr-only">Stemming</span>
          </th>
          <th
            scope="col"
            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
          >
            From
          </th>
          <th
            scope="col"
            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
          >
            To
          </th>
          <th
            scope="col"
            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
          >
            Status
          </th>
          <th scope="col" class="relative px-6 py-3">
            <span class="sr-only">Edit</span>
          </th>
        </tr>
      </thead>

      <tbody class="bg-white divide-y divide-gray-200">
        <tr v-for="(rule, index) in data.rules" :key="index">
          <td
            class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"
          >
            <icon-ban class="w-6 h-6"></icon-ban>
          </td>
          <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
            {{ rule.word }} ->
          </td>
          <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
            {{ rule.stemmed }}
          </td>

          <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
            <div
              v-if="true"
              class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 cursor-pointer w-16 text-center"
            >
              <span class="mx-auto">Active</span>
            </div>
            <div
              v-else
              class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 cursor-pointer w-16 text-center"
            >
              <span class="mx-auto">Inactive</span>
            </div>
          </td>
          <td
            class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium"
          >
            <a
              href="#"
              class="text-theme-orange-light-900 hover:text-indigo-900"
              >Delete</a
            >
          </td>
        </tr>

        <tr>
          <td colspan="5">
            <pagination></pagination>
          </td>
        </tr>
      </tbody>
    </table>

    <table class="min-w-full divide-y divide-gray-200 mt-5">
      <thead class="bg-gray-50">
        <tr>
          <th
            scope="col"
            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
          >
            <span class="sr-only">Stemming</span>
          </th>
          <th
            scope="col"
            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
          >
            Word
          </th>
          <th
            scope="col"
            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
          >
            Status
          </th>
          <th scope="col" class="relative px-6 py-3">
            <span class="sr-only">Edit</span>
          </th>
        </tr>
      </thead>
      <tbody class="bg-white divide-y divide-gray-200">
        <tr v-for="(word, index) in data.keywords" :key="index">
          <td
            class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"
          >
            <icon-ban class="w-6 h-6"></icon-ban>
          </td>
          <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
            {{ word.word }}
          </td>

          <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
            <div
              v-if="true"
              class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 cursor-pointer w-16 text-center"
            >
              <span class="mx-auto">Active</span>
            </div>
            <div
              v-else
              class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 cursor-pointer w-16 text-center"
            >
              <span class="mx-auto">Inactive</span>
            </div>
          </td>
          <td
            class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium"
          >
            <a
              href="#"
              class="text-theme-orange-light-900 hover:text-indigo-900"
              >Delete</a
            >
          </td>
        </tr>

        <tr>
          <td colspan="5">
            <pagination></pagination>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script>
import pagination from "./_pagination";

export default {
  props: ["data"],
  components: {
    pagination,
  },
  mounted() {},
};
</script>

<style scoped>
</style>
