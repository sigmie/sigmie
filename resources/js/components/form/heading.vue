<template>
  <h1 class="pt-5 pb-2 text-xl">{{ text }}</h1>
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
