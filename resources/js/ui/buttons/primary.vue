<template>
    <button
      :id="id"
      :type="type"
      :disabled="disabled"
      @click="$emit('click', $event)"
      :class="disabled ? 'cursor-not-allowed bg-orange-200' : 'bg-orange-600'"
      class="w-full rounded-md shadow-sm justify-center py-2 px-4 border border-transparent text-sm font-medium text-white hover:shadow-sm transition-shadow focus:outline-none bg-theme-primary bg-orange-600 focus:bg-orange-700 focus:shadow-outline-indigo active:bg-orange-700"
    >{{ text }}</button>
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
