<template>
  <div class="flex flex-col">
    <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
      <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
        <div
          class="shadow overflow-hidden border-b border-gray-200 sm:rounded-lg"
        >
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th
                  scope="col"
                  class="p-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                >
                  ID
                </th>
                <th
                  v-for="(mapping, index) in mappings"
                  :key="index"
                  scope="col"
                  class="p-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                >
                  {{ mapping }}
                </th>
                <th
                  scope="col"
                  class="p-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                >
                  Score
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                @click="() => viewResult(result)"
                v-for="(result, index) in results"
                :key="index"
                :class="index % 2 === 0 ? 'bg-white' : 'bg-gray-50'"
              >
                <td
                  class="p-2 whitespace-nowrap text-sm font-medium text-gray-500"
                >
                  <div>
                    {{ result._id }}
                  </div>
                </td>
                <td
                  v-for="mapping in mappings"
                  :key="result._id + '_' + mapping"
                  class="p-2 flex-wrap text-sm font-medium text-gray-500"
                >
                  <div class="max-h-24 max-w-md overflow-auto">
                    {{ result._source[mapping] }}
                  </div>
                </td>
                <td class="p-2 whitespace-nowrap text-sm text-gray-500">
                  {{ result._score }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <result :result="selected"></result>
  </div>
</template>

<script>
import includes from "lodash/includes";
import result from "./_result";

export default {
  components: {
    result,
  },
  props: ["results", "mappings"],
  mounted() {},
  data() {
    return {
      selected: null,
    };
  },
  methods: {
    inArray(needle, haystack) {
      return includes(haystack, needle);
    },
    viewResult(result) {
      this.selected = resutl;
    },
  },
};
</script>

<style scoped>
</style>

