<template>
  <app title="Payment">
    <div class="flex content-center justify-center h-128">
      <div class="self-center">
        <p class="p-6 text-cool-gray-500 text-center">
          Your subscription isn't valid yet. <br>
          Click
          <inertia-link class="text-orange-500" :href="$route('subscription.create')">here</inertia-link> to conclude your subscription.
        </p>
      </div>
    </div>
  </app>
</template>
<script>
import App from "../layouts/app";

export default {
  components: {
    App,
  },
};
</script>

<style scoped>
</style>