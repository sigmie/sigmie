<template>
  <div class="bg-white border-gray-200 text-left sm:px-6">
    <h3 class="text-lg leading-6 font-medium text-gray-900">{{ text}}</h3>
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
