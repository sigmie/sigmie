<template>
  <div>
    <inertia-link :href="$route('cluster.edit',{cluster: clusterId})" class="cursor-pointer">
      <p class="p-6 text-gray-800 border-dashed border-2 text-center">+ Restore cluster</p>
    </inertia-link>
  </div>
</template>

<script>
export default {};
</script>

<style scoped>
</style>