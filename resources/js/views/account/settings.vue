<template>
  <app title="Settings">
    <div class="mx-auto max-w-xl">
      <h1 class="leading-6 text-2xl font-medium text-gray-900 mb-10">Settings</h1>
      <div class="flex flex-col content-center justify-center">
        <div>
          <navigation :section="selected" @change="updateSelected"></navigation>
        </div>
        <component :data="data[selected]" class="mt-5" :is="selected"></component>
      </div>
    </div>
  </app>
</template>

<script>
import App from "../layouts/app";
export default {
  props: ["section", "data"],
  components: {
    App,
    navigation: require("./navigation").default,
    account: require("./account").default,
    general: require("./general").default,
    notifications: require("./notifications").default,
    subscription: require("./subscription").default,
  },
  data() {
    return {
      selected: this.section,
    };
  },
  methods: {
    updateSelected(value) {
      this.selected = value;
    },
  },
};
</script>

<style scoped>
</style>