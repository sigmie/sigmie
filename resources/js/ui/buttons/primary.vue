<template>
  <span class="block w-full rounded-md shadow-sm">
    <button
      :id="id"
      :type="type"
      :disabled="disabled"
      @click="$emit('click', $event)"
      class="w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-orange-600 hover:shadow-sm transition-shadow focus:outline-none focus:bg-orange-700 focus:shadow-outline-indigo active:bg-orange-700"
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
