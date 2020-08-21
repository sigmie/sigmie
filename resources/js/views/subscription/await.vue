<template>
  <layout title="Sign Up">
    <div class="flex content-center justify-center h-128">
      <div class="self-center">
        <spinner class="mx-auto block" color="cool-gray-400"></spinner>
        <p class="p-6 text-cool-gray-500 text-center">{{ text }}</p>
      </div>
    </div>
  </layout>
</template>
<script>
import Layout from "../layouts/app";

export default {
  props: ["checkoutId"],
  data() {
    return {
      text: "Waiting for authorization from our payment provider...",
    };
  },
  components: {
    Layout,
  },
  async mounted() {
    this.$socket
      .private(`user.${this.$page.user.id}`)
      .listen("Subscription\\UserWasSubscribed", (e) => {
        this.text = "Authorization recieved, redirecting...";
        this.reload();
      });

    let response = await this.$http.get(this.$route("subscirption.check"));

    if (response.data.subscribed) {
      this.text = "Authorization recieved, redirecting...";
      this.reload();
    }
  },
  methods: {
    reload() {
      this.$inertia.reload({
        method: "get",
        data: {},
        preserveState: false,
        preserveScroll: false,
        only: [],
      });
    },
  },
};
</script>

<style scoped>
</style>