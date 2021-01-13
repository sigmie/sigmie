<template>
  <span class="inline-flex rounded-md shadow-sm w-full">
    <button
      :id="id"
      :type="type"
      :disabled="disabled"
      @click="$emit('click', $event)"
      class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm leading-5 font-medium rounded-md text-gray-700 bg-white hover:text-gray-500 focus:outline-none focus:border-blue-300 focus:ring-blue active:text-gray-800 active:bg-gray-50 transition ease-in-out duration-150"
    >{{ text }}</button>
  </span>
</template>

<script>
export default {
  props: {
    id: {
      default: ""
    },
    type: {
      default: ""
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
