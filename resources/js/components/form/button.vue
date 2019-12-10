<template>
  <button
    :id="id"
    :type="type"
    :disabled="disabled"
    v-bind:class="[disabled ? 'bg-blue-200' : 'bg-blue-800 hover:bg-blue-900']"
    class="text-white text-sm py-2 px-4 rounded uppercase w-full float-right font-semibold tracking-wide"
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

