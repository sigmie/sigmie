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
  methods: {
    getToken() {
      let csrfMeta = document.head.querySelector('meta[name="csrf-token"]');
      return csrfMeta.content;
    }
  },
  mounted() {
    this.token = this.getToken();
  }
};
</script>
