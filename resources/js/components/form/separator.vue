<template>
  <div class="px-10 border-t mt-3 pt-1 w-full">
    <span class="text-xs text-gray-500">{{ text }}</span>
  </div>
</template>

<script>
export default {
  props: {
    text: {
      default: ""
    }
  }
};
</script>
