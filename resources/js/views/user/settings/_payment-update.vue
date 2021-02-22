<template>
  <div
    class="border-gray-200 w-full bg-white shadow overflow-hidden rounded-lg"
  >
    <div class="flex justify-between px-6 py-5">
      <div class>
        <div class="font-semibold text-base text-gray-800">Payment method</div>
        <div v-if="currentType === 'card'" class="text-sm text-gray-600">
          Your current payment method is a credit card ending at
          {{ lastFour }} that expires on {{ expireDate }}.
        </div>
        <div v-else-if="currentType === 'paypal'" class="text-sm text-gray-600">
          Your current payment method is Paypal.
        </div>
      </div>
      <div class="max-w-sm py-1">
        <a
          @click.prevent="openPaddle"
          href="#!"
          class="inline-flex justify-center w-full bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
          :data-override="url"
          data-success="https://example.com/subscription/update/success"
          >Update</a
        >
      </div>
    </div>
  </div>
</template>

<script>
export default {
  props: ["url", "vendor", "expireDate", "lastFour",  "currentType"],
  methods: {
    handleClose() {},
    handleComplete() {
      this.$inertia.reload({ only: ["data"] });
    },
    openPaddle() {
      Paddle.Checkout.open({
        override: this.url,
        closeCallback: this.handleClose,
        successCallback: this.handleComplete,
      });
    },
  },
  mounted() {
    let script = document.createElement("script");

    script.onload = () => {
      Paddle.Setup({ vendor: this.vendor });
    };

    script.src = "https://cdn.paddle.com/paddle/paddle.js";

    document.head.appendChild(script);
  },
};
</script>

<style scoped>
</style>
