<template>
  <input type="hidden" name="_token" v-model="token" />
</template>

<script>
export default {
  data() {
    return {
      token: null
    };
  },
  mounted() {
    let csrfMeta = document.head.querySelector('meta[name="csrf-token"]');
    this.token = csrfMeta.content;
  }
};
</script>

