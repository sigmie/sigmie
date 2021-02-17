<template>
  <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="bg-white shadow overflow-hidden rounded-md">
      <ul class="divide-y divide-gray-200 overflow-auto max-h-128">
        <li
          class="block text-gray-500 w-full p-18 text-center hover:bg-gray-50"
          v-if="activities.length < 1"
        >
          There aren't any activities.
        </li>

        <activity
          v-for="(activity, index) in activities"
          :activity="activity"
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
        return [];
      },
    },
  },
};
</script>

<style scoped>
</style>
