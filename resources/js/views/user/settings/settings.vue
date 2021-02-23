<template>
  <app title="Settings">
    <div class="mx-auto max-w-xl">
      <h1 class="leading-6 text-2xl font-medium text-gray-900 mb-10">
        Account Settings
      </h1>
      <div class="flex flex-col content-center justify-center">
        <div>
          <navigation :section="section"></navigation>
        </div>
        <component :data="data" class="mt-5" :is="section"></component>
      </div>
    </div>
  </app>
</template>

<script>
import App from "../../layouts/app";
import navigation from "./_navigation";
import account from "./_account";
import subscription from "./_subscription";

export default {
  props: ["section", "data"],
  components: {
    App,
    navigation,
    account,
    subscription,
  },
};
</script>

<style scoped>
</style>
