<template>
  <button
    :id="id"
    :type="type"
    :disabled="disabled"
    @click="$emit('click', $event)"
    :class="disabled ? 'cursor-not-allowed bg-red-200' : 'bg-red-500'"
    class="inline-flex justify-center w-full rounded-md border border-transparent px-4 py-2 bg-red-500 text-base leading-6 font-medium text-white shadow-sm hover:bg-red-600 focus:outline-none focus:border-red-700 focus:shadow-outline-red transition ease-in-out duration-150 sm:text-sm sm:leading-5"
  >{{ text }}</button>
</template>

<script>
export default {
  props: {
    id: {
      default: ""
    },
    type: {
      default: "button"
    },
    text: {
      default: ""
    },
    disabled: {
      default: false
    }
  }
};
</script>

<style scoped>
</style>
