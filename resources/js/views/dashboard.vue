<template>
  <app>
    <div class="pt-4" v-if="state === 'queued_create' || state === 'created'">
      <spinner class="mx-auto block"></spinner>
      <p
        class="p-6 text-gray-800 text-center"
      >Your cluster is being created. This may take a while...</p>
    </div>

    <div class="pt-4" v-if="state === 'queued_destroy'">
      <spinner class="mx-auto block"></spinner>
      <p class="p-6 text-gray-800 text-center">Your cluster is being destroyed...</p>
    </div>

    <div v-if="state === 'running'">Running cluster info</div>

    <div v-if="state === 'destroyed'" class="max-w-md mx-auto">
      <inertia-link :href="$route('cluster.edit',{cluster: id})" class="cursor-pointer">
        <p class="p-6 text-gray-800 border-dashed border-2 text-center">+ Restore cluster</p>
      </inertia-link>
    </div>

    <div v-if="state === null" class="max-w-md mx-auto">
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
  props: ["state", "id"],
  components: { App },
  beforeMount() {
    if (this.id === null) {
      return;
    }

    this.$socket.private(`cluster.${this.id}`).listen("ClusterIsRunning", e => {
      // Wait 5 seconds for the cluster state to change
      // in the database before reloading the page
      delay(() => {
        this.$inertia.reload({
          method: "get",
          data: {},
          preserveState: false,
          preserveScroll: false,
          only: []
        });
      }, 5);
    });

    this.$socket
      .private(`cluster.${this.id}`)
      .listen("ClusterWasDestroyed", e => {
        // Wait 5 seconds for the cluster state to change
        // in the database before reloading the page
        delay(() => {
          this.$inertia.reload({
            method: "get",
            data: {},
            preserveState: false,
            preserveScroll: false,
            only: []
          });
        }, 5);
      });
  }
};
</script>

<style>
</style>
