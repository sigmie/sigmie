<template>
  <table class="min-w-full divide-y divide-gray-200 mt-5">
    <thead class="bg-gray-50">
      <tr>
        <th
          scope="col"
          class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
        >
          <span class="sr-only">Icon</span>
        </th>
        <th
          scope="col"
          class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
        >
          Field
        </th>
        <th
          scope="col"
          class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
        >
          Type
        </th>
        <th
          scope="col"
          class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
        >
          Analyzer
        </th>
      </tr>
    </thead>
    <tbody v-if="indexName" class="bg-white divide-y divide-gray-200">
      <tr
        v-for="(fieldData, field) in data[indexName].mappings.properties"
        :key="field"
      >
        <td
          class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"
        >
          <icon-sun class="w-6 h-6"></icon-sun>
        </td>

        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
          {{ field }}
        </td>

        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
          {{ fieldData.type }}
        </td>

        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
          {{ fieldData.analyzer }}
        </td>
      </tr>

      <tr>
        <td colspan="5">
          <pagination></pagination>
        </td>
      </tr>
    </tbody>
  </table>
</template>

<script>
import pagination from "./_pagination";

export default {
  props: ["data", "indices", "indexName"],
  components: {
    pagination,
  },
  methods: {
    onIndexChange() {},
  },
  mounted() {},
};
</script>

<style scoped>
</style>
