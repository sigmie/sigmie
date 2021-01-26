<template>
  <li
    @click="()=> $emit('createRequest')"
    class="relative col-span-1 h-14 flex shadow-sm rounded-md border-t border-r border-b border-gray-200 bg-white hover:shadow-md cursor-pointer"
  >
    <div class="flex-1 flex items-center justify-between rounded-r-md truncate pl-4">

      <span class="h-6 w-6 text-green-400">
          <icon-plus></icon-plus>
      </span>
      <div class="flex-1 px-4 py-2 text-sm truncate">
        <div class="text-gray-900 font-medium hover:text-gray-600">
            New Plan
        </div>
      </div>
    </div>
  </li>
</template>

<script>
export default {};
</script>

<style scoped>
</style>
