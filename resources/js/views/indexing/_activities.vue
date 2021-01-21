<template>
  <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
    <!-- This example requires Tailwind CSS v2.0+ -->
    <div class="bg-white shadow overflow-hidden rounded-md">
      <ul class="divide-y divide-gray-200">
          <activity
            v-for="(activity, index) in activities"
            :key="index"
          ></activity>
      </ul>
    </div>
  </div>
</template>

<script>
import Activity from "./_activities/_activity";
export default {
  components: {
    Activity,
  },
  props: {
    activities: {
      default() {
        return [{}, {}, {}, {}];
      },
    },
  },
};
</script>

<style scoped>
</style>
