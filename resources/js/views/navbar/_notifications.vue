<template>
  <div
    class="py-1 rounded-md bg-white ring-1 ring-black ring-opacity-5 overflow-auto max-h-128"
    v-on-clickaway="emitAway"
  >
    <ul v-cloak>
      <li
        v-for="(notification, index) in notifications"
        :key="index"
        class="border-t border-gray-200 first:border-t-0 hover:bg-gray-50 focus:bg-gray-50"
      >
        <a
          @click.prevent="
            notification.read_at === null
              ? readNotification(index, notification.id)
              : () => {}
          "
          :class="[notification.read_at === null ? 'bg-gray-100' : '']"
          class="block cursor-pointer focus:outline-none transition duration-150 ease-in-out"
        >
          <div class="px-4 py-4 sm:px-6">
            <div class="flex items-center justify-between">
              <div
                class="text-sm leading-5 font-medium text-theme-orange-light-900 truncate"
              >
                {{ notification.data.title }}
              </div>
              <div class="ml-2 flex-shrink-0 flex">
                <span
                  class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-theme-orange-light-100 text-theme-orange-light-800"
                  >#{{
                    notification.data.project.toLowerCase().replace(" ", "_")
                  }}</span
                >
              </div>
            </div>
            <div class="mt-2 sm:flex sm:justify-between">
              <div class="sm:flex">
                <div
                  class="mr-6 flex items-center text-sm leading-5 text-gray-500"
                >
                  <span v-html="notification.data.body"></span>
                </div>
              </div>
              <div
                class="mt-2 flex items-center text-sm leading-5 text-gray-400 sm:mt-0"
              >
                <span class="w-full md:w-24 text-right text-xs">
                  <time :datetime="notification.create_at">{{
                    relativeTime(notification.created_at)
                  }}</time>
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
import includes from "lodash/includes";
import { mixin as clickaway } from "vue-clickaway2";

export default {
  mixins: [clickaway],
  props: {
    notifications: {
      default: [],
    },
  },
  data() {
    return {
      read: [],
    };
  },
  methods: {
    relativeTime(utcDatetime) {
      return moment.utc(utcDatetime).local().fromNow();
    },
    readNotification(index, id) {
      if (includes(this.read, id)) {
        return;
      }

      this.$emit("read", index);

      this.markAsRead(index, id);
    },
    emitAway() {
      this.$emit("away");
    },
    async markAsRead(index, id) {
      const route = this.$route('notification.update',{notification: id});
      const response = await this.$http.put(route);
    },
  },
};
</script>

<style>
</style>
