<template>
  <a
    href="#!"
    data-theme="none"
    class="paddle_button w-full block text-center rounded-md shadow-sm justify-center py-2 px-4 border border-transparent text-sm font-medium text-white hover:shadow-sm transition-shadow focus:outline-none bg-theme-primary bg-orange-600 focus:bg-orange-700 focus:shadow-outline-indigo active:bg-orange-700"
    data-override="paylink"
  >Create account</a>
</template>

<script>

export default {
  props: ["paylink", "vendor"],
  methods: {
    handleClose() {
      this.$inertia.visit(this.$route("dashboard"));
    },
    handleComplete() {
      this.$inertia.visit(this.$route("subscription.await"));
    },
  },
  mounted() {
    let vendor = this.vendor;
    let paylink = this.paylink;
    let script = document.createElement("script");
    let handleClose = this.handleClose;
    let handleComplete = this.handleComplete;

    script.onload = () => {
      Paddle.Setup({ vendor: vendor });

      Paddle.Checkout.open({
        override: paylink,
        closeCallback: handleClose,
        successCallback: handleComplete,
      });
    };

    script.src = "https://cdn.paddle.com/paddle/paddle.js";

    document.head.appendChild(script);
  },
};
</script>

<style>
</style>
