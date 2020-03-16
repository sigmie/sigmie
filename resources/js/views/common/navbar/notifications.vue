<template>
  <div class="py-1 rounded-md bg-white shadow-xs">
    <ul v-cloak>
      <li
        v-for="(notification,index) in notifications"
        :key="index"
        class="border-t border-gray-200 first:border-t-0"
      >
        <a
          @click.prevent="(notification.read_at === null) ? makrkAsRead(index, notification.id): ()=>{}"
          :class="[(notification.read_at === null) ? 'bg-gray-100':'']"
          class="block cursor-pointer hover:bg-gray-50 focus:outline-none focus:bg-gray-50 transition duration-150 ease-in-out"
        >
          <div class="px-4 py-4 sm:px-6">
            <div class="flex items-center justify-between">
              <div
                class="text-sm leading-5 font-medium text-orange-600 truncate"
              >{{ notification.data.title }}</div>
              <div class="ml-2 flex-shrink-0 flex">
                <span
                  class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-orange-100 text-orange-700"
                >#{{ notification.data.project.title.toLowerCase().replace(' ', '_') }}</span>
              </div>
            </div>
            <div class="mt-2 sm:flex sm:justify-between">
              <div class="sm:flex">
                <div class="mr-6 flex items-center text-sm leading-5 text-gray-500">
                  <span v-html="notification.data.body"></span>
                </div>
              </div>
              <div class="mt-2 flex items-center text-sm leading-5 text-gray-400 sm:mt-0">
                <span class="w-20 text-right text-xs">
                  <time :datetime="notification.create_at">{{relativeTime(notification.created_at) }}</time>
                </span>
              </div>
            </div>
          </div>
        </a>
      </li>
    </ul>
  </div>
</template>

<script>
import moment from "moment";

export default {
  props: {
    notifications: {
      default: []
    }
  },
  methods: {
    relativeTime(datetime) {
      return moment(datetime, "YYYY-MM-DD H:mm:S").fromNow();
    },
    async makrkAsRead(index, id) {
      this.$set(
        this.notifications[index],
        "read_at",
        moment().format("YYYY-MM-DD H:mm:S")
      );

      const response = await this.$http.put(`notification/${id}`);
    }
  }
};
</script>

<style>
</style>
