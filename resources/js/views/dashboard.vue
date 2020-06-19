<template>
  <app>
    <div v-if="data">if data</div>
    <div v-else class="max-w-md mx-auto">
      <inertia-link
        :href="$route('cluster.create',{project_id: $page.project })"
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
  props: ["data"],
  components: { App }
};
</script>

<style>
</style>
