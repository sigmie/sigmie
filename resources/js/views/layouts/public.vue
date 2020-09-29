<template>
  <layout :title="title">
    <div class="min-h-full relative pb-20">
      <main class="mx-auto container m-0 w-256 max-w-full">
        <slot></slot>
      </main>

      <footer
        class="bottom-0 absolute text-cool-gray-500 text-sm w-full h-20 pt-5"
      >
        <div class="text-center md:px-7 md:space-x-8 mx-auto w-full justify-center flex-wrap flex">
          <div class="pb-3 mx-auto md:mx-0 block px-3">
            <inertia-link :href="$route('legal.about')">About Us</inertia-link>
          </div>
          <div class="pb-3 mx-auto block px-3">
            <inertia-link :href="$route('legal.terms')"
              >Terms of Service</inertia-link
            >
          </div>
          <div class="pb-3 mx-auto block px-3">
            <inertia-link :href="$route('legal.privacy')"
              >Privacy Policy</inertia-link
            >
          </div>
          <div class="pb-3 mx-auto block px-3">
            <inertia-link :href="$route('legal.imprint')">Imprint</inertia-link>
          </div>
          <div class="pb-3 mx-auto block px-3">
            <inertia-link :href="$route('legal.disclaimer')"
              >Disclaimer</inertia-link
            >
          </div>
        </div>
      </footer>
    </div>
  </layout>
</template>

<script>
import Layout from "./layout";

export default {
  props: ["title"],
  components: {
    Layout,
  },
};
</script>

<style scoped>
</style>
