<template>
  <app>
    <div class="pt-4" v-if="state === 'queued_create'">
      <spinner class="mx-auto block"></spinner>
      <p
        class="p-6 text-gray-800 text-center"
      >Your cluster is being created. This may take a while...</p>
    </div>

    <div class="pt-4" v-if="state === 'queued_destroyed'">
      <spinner class="mx-auto block"></spinner>
      <p
        class="p-6 text-gray-800 text-center"
      >Your cluster is being destroyed...</p>
    </div>

    <div v-else-if="state === 'running'">Running cluster info</div>
    <div v-else-if="state === null" class="max-w-md mx-auto">
      <inertia-link
        :href="$route('cluster.create',{project_id: $page.project_id })"
        class="cursor-pointer"
      >
        <p class="p-6 text-gray-800 border-dashed border-2 text-center">+ Add new cluster</p>
      </inertia-link>
    </div>
  </app>
</template>

<script>
import App from "./layouts/app";
export default {
  props: ["state"],
  components: { App }
};
</script>

<style>
</style>
