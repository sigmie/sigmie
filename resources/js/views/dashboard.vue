<template>
  <app title="Dashboard">
    <div class="pt-4" v-if="clusterState === 'queued_create' || clusterState === 'created'">
      <spinner class="mx-auto block"></spinner>
      <p
        class="p-6 text-gray-800 text-center"
      >Your cluster is being created. This may take a while...</p>
    </div>

    <div class="pt-4" v-if="clusterState === 'queued_destroy'">
      <spinner class="mx-auto block"></spinner>
      <p class="p-6 text-gray-800 text-center">Your cluster is being destroyed...</p>
    </div>

    <div v-if="clusterState === 'running'">
      <div class="mt-8">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
          <h2 class="text-lg leading-6 font-medium text-cool-gray-900">Overview</h2>
          <div class="mt-2 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <!-- Card -->

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
        </div>
      </div>
    </div>

    <div v-if="clusterState === 'destroyed'" class="max-w-md mx-auto">
      <inertia-link :href="$route('cluster.edit',{cluster: clusterId})" class="cursor-pointer">
        <p class="p-6 text-gray-800 border-dashed border-2 text-center">+ Restore cluster</p>
      </inertia-link>
    </div>

    <div v-if="clusterState === null" class="max-w-md mx-auto">
      <inertia-link
        :href="$route('cluster.create',{project_id: $page.project_id })"
        class="cursor-pointer"
      >
        <p class="p-6 text-gray-800 border-dashed border-2 text-center">+ Add cluster</p>
      </inertia-link>
    </div>
  </app>
</template>

<script>
import App from "./layouts/app";
import delay from "lodash/delay";
export default {
  props: ["clusterState", "clusterId"],
  components: { App },
  beforeMount() {
    if (this.clusterId === null) {
      return;
    }

    this.$socket
      .private(`cluster.${this.clusterId}`)
      .listen("ClusterWasBooted", (e) => {
        // Wait 5 seconds for the cluster state to change
        // in the database before reloading the page
        delay(() => {
          this.$inertia.reload({
            method: "get",
            data: {},
            preserveState: false,
            preserveScroll: false,
            only: [],
          });
        }, 5);
      });

    this.$socket
      .private(`cluster.${this.clusterId}`)
      .listen("ClusterWasDestroyed", (e) => {
        // Wait 5 seconds for the cluster state to change
        // in the database before reloading the page
        delay(() => {
          this.$inertia.reload({
            method: "get",
            data: {},
            preserveState: false,
            preserveScroll: false,
            only: [],
          });
        }, 5);
      });
  },
};
</script>

<style>
</style>
