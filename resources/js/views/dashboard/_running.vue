<template>
  <div>
    <div class="mt-8">
      <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-lg leading-6 font-medium text-cool-gray-900">Overview</h2>
        <div class="mt-2 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
          <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
              <div class="flex items-center">
                <div class="flex-shrink-0">
                  <icon-heart class="h-6 w-6 text-gray-300"></icon-heart>
                </div>
                <div class="ml-5 w-0 flex-1">
                  <dl>
                    <dt
                      class="text-sm leading-5 font-medium text-cool-gray-500 truncate"
                    >Cluster health</dt>
                    <dd>
                      <div
                        class="text-lg leading-7 font-medium text-cool-gray-900"
                      >{{ clusterInfo.health }}</div>
                    </dd>
                  </dl>
                </div>
              </div>
            </div>
          </div>

          <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
              <div class="flex items-center">
                <div class="flex-shrink-0">
                  <icon-heart class="h-6 w-6 text-gray-300"></icon-heart>
                </div>
                <div class="ml-5 w-0 flex-1">
                  <dl>
                    <dt
                      class="text-sm leading-5 font-medium text-cool-gray-500 truncate"
                    >Nodes count</dt>
                    <dd>
                      <div
                        class="text-lg leading-7 font-medium text-cool-gray-900"
                      >{{ clusterInfo.nodesCount }}</div>
                    </dd>
                  </dl>
                </div>
              </div>
            </div>
          </div>

          <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
              <div class="flex items-center">
                <div class="flex-shrink-0">
                  <icon-heart class="h-6 w-6 text-gray-300"></icon-heart>
                </div>
                <div class="ml-5 w-0 flex-1">
                  <dl>
                    <dt
                      class="text-sm leading-5 font-medium text-cool-gray-500 truncate"
                    >Cluster health</dt>
                    <dd>
                      <div class="text-lg leading-7 font-medium text-cool-gray-900">green</div>
                    </dd>
                  </dl>
                </div>
              </div>
            </div>
          </div>

          <!-- More cards... -->
        </div>

        <div class="flex flex-col mt-5">
          <div class="-my-2 py-2 overflow-x-auto sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
            <div
              class="align-middle inline-block min-w-full shadow overflow-hidden sm:rounded-lg border-b border-gray-200"
            >
              <table class="min-w-full divide-y divide-gray-200">
                <thead>
                  <tr>
                    <th
                      class="px-6 py-3 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider"
                    >Name</th>
                    <th
                      class="px-6 py-3 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider"
                    >Size</th>
                    <th
                      class="px-6 py-3 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider"
                    >Documents</th>
                  </tr>
                </thead>
                <tbody v-if="indices.length > 0">
                  <tr
                    v-for="(esIndex, index) in indices"
                    :key="index"
                    :class="(index % 2 === 0)? 'bg-white':'bg-gray-50'"
                  >
                    <td
                      class="px-6 py-4 whitespace-no-wrap text-sm leading-5 font-medium text-gray-900"
                    >{{ esIndex.name }}</td>
                    <td
                      class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500"
                    >{{ esIndex.size }}</td>
                    <td
                      class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500"
                    >{{ esIndex.docsCount}}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  props: ["clusterInfo", "indices"],
};
</script>

<style scoped>
</style>