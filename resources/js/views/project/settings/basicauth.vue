<template>
  <div
    class="pt-5 shadow mx-auto bg-white rounded-md sm:overflow-hidden max-w-lg mt-6"
  >
    <div class="">
      <div class="mb-3 px-6">
        <h3 class="text-md self-start leading-6 font-medium text-gray-900">
          Authentication
        </h3>
      </div>
      <dl class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-1 border-t">
        <div class="sm:col-span-1 px-6 pt-4">
          <dt class="text-sm font-medium text-gray-500">Username:</dt>
          <dd class="mt-1 text-sm text-gray-900">{{ username }}</dd>
        </div>
        <div class="sm:col-span-1 bg-gray-50 px-6 py-2">
          <dt class="text-sm font-medium text-gray-500">Password:</dt>
          <dd class="mt-1 text-sm text-gray-900">**************</dd>
        </div>
      </dl>
      <div class="sm:col-span-1 px-6 py-4">
        <div class="w-full md:w-1/3">
          <button-secondary
            :disabled="disabled"
            :text="disabled ? 'Updating...' : 'Edit'"
          ></button-secondary>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  props: ["username"],
};
</script>

<style scoped>
</style>
