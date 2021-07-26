<template>
  <button
    :id="id"
    :type="type"
    :disabled="disabled"
    @click="$emit('click', $event)"
    :class="
      disabled
        ? 'cursor-not-allowed border-gray-100 text-gray-400'
        : 'bg-white shadow-sm border-gray-300 text-gray-700 hover:bg-gray-50 '
    "
    class="py-2 px-4 border rounded-md text-sm font-medium focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 w-full"
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
