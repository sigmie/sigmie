<template>
    <h2 class="pt-3 pb-1 border-b tracking-widest font-semibold w-full text-sm px-10 uppercase text-gray-500">{{ text }}</h2>
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
