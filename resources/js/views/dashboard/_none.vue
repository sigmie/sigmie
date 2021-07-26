<template>
  <div>
    <inertia-link
      :href="$route('cluster.create',{project_id: $page.props.project_id })"
      class="cursor-pointer"
    >
      <p class="p-6 text-gray-800 border-dashed border-2 text-center">+ Add cluster</p>
    </inertia-link>
  </div>
</template>

<script>
export default {};
</script>

<style scoped>
</style>
