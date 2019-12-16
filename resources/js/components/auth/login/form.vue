<template>
  <form
    class="mx-auto flex container w-84 text-gray-700 h-auto"
    method="POST"
    :action="action"
  ></form>
</template>

<script>
export default {
  props: ["action"],
  data() {
    return {
      mutableErrors: { ...this.errors }
    };
  }
};
</script>
