<template>
  <app>
    <div class="mt-8">
      <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-lg leading-6 font-medium text-gray-900">Plans</h2>

        <!-- Plans -->
        <plans></plans>

        </div>
      </div>

      <h2
        class="max-w-6xl mx-auto mt-8 px-4 text-lg leading-6 font-medium text-gray-900 sm:px-6 lg:px-8"
      >
        Recent activity
      </h2>

<activity></activity>
    </div>
  </app>
</template>

<script>
import App from "../layouts/app";
import Plans from "./_plans";
import Activity from "./_activity";

export default {
  components: {
    App,
    Plans,
    Activity
  },
};
</script>

<style scoped>
</style>
