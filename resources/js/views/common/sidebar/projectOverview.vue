<template>
  <div class="border-gray-600 border-b">
    <div class="px-6 py-2">
      <div class="space-y-1">
        <div class="text-base leading-5 font-medium text-white" v-if="projectName">{{ projectName }}</div>
        <div class="text-base leading-5 font-medium text-center text-cool-gray-400" v-else>- no project -</div>
        <a
          :href="clusterUrl"
          v-if="clusterUrl"
          target="_blank"
          class="group flex items-center space-x-2.5"
        >
          <div class="text-sm leading-5 text-cool-gray-400 font-medium">{{ clusterUrl }}</div>
        </a>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  props: ["projectName", "clusterUrl"],
};
</script>

<style scoped>
</style>