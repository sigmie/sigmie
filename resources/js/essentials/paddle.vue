<template>
  <a
    href="#!"
    data-theme="none"
    class="paddle_button w-full block text-center rounded-md shadow-sm justify-center py-2 px-4 border border-transparent text-sm font-medium text-white hover:shadow-sm transition-shadow focus:outline-none bg-theme-primary bg-orange-600 focus:bg-orange-700 focus:shadow-outline-indigo active:bg-orange-700"
    data-override="paylink"
  >Create account</a>
</template>

<script>
import loginVue from "../views/auth/login.vue";

export default {
  props: ["paylink", "data"],
  beforeMount() {
    let paddleScript = document.createElement("script");
    paddleScript.setAttribute("src", "https://cdn.paddle.com/paddle/paddle.js");
    paddleScript.async = true;
    paddleScript.addEventListener("load", () => {
      Paddle.Setup({ vendor: this.data.vendor });
    });
    document.head.appendChild(paddleScript);
  },
};
</script>

<style>
</style>
