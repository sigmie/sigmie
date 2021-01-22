<template>
  <button
    :id="id"
    :type="type"
    :disabled="disabled"
    @click="$emit('click', $event)"
    class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 w-full"
  >
    {{ text }}
  </button>
</template>

<script>
export default {
  props: {
    id: {
      default: "",
    },
    type: {
      default: "",
    },
    text: {
      default: "",
    },
    disabled: {
      default: false,
    },
  },
};
</script>
