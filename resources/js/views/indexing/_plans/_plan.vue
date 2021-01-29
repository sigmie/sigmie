<template>
  <li
    class="relative col-span-1 flex shadow-sm rounded-md border-t border-r border-b border-gray-200 bg-white"
  >
    <div
      class="flex-1 flex items-center justify-between rounded-r-md truncate pl-4"
    >
      <span
        v-if="plan.deactivated_at === null"
        class="h-2 w-2 bg-green-400 rounded-full"
      ></span>
      <span v-else class="h-2 w-2 bg-red-400 rounded-full"></span>
      <div class="flex-1 px-4 py-2 text-sm truncate">
        <a href="#" class="text-gray-900 font-medium hover:text-gray-600">
          {{ plan.name }}
        </a>
        <p
          v-if="plan.state === 'none' && plan.run_at !== null"
          class="text-gray-500"
        >
          {{ relativeTime(plan.run_at) }}
        </p>
        <p v-else-if="plan.run_at === null" class="text-gray-500">Never</p>
        <p v-else class="text-gray-500">Running...</p>
      </div>
      <div class="flex-shrink-0 pr-2">
        <button
          @click="show = !show"
          id="pinned-project-options-menu-0"
          aria-haspopup="true"
          class="w-8 h-8 bg-white inline-flex items-center justify-center text-gray-400 rounded-full hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500"
        >
          <span class="sr-only">Open options</span>
          <!-- Heroicon name: dots-vertical -->
          <svg
            class="w-5 h-5"
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 20 20"
            fill="currentColor"
            aria-hidden="true"
          >
            <path
              d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"
            />
          </svg>
        </button>
        <div
          v-on-clickaway="() => (show = false)"
          :class="show ? 'block' : 'hidden'"
          class="z-10 mx-3 origin-top-right absolute right-10 top-3 w-48 mt-1 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 divide-y divide-gray-200"
          role="menu"
          aria-orientation="vertical"
          aria-labelledby="pinned-project-options-menu-0"
        >
          <div class="py-1" role="none">
            <button
              class="group w-full text-left flex px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900"
              @click="editRequest"
              role="menuitem"
            >
              Edit
            </button>

            <button
              v-clipboard="() => plan.webhook_url"
              v-clipboard:success="() => onCopy()"
              class="group w-full text-left flex px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900"
              role="menuitem"
            >
              {{ copying ? "Copied!" : "Copy Webhook Url" }}
            </button>
          </div>
          <div class="py-1" role="none">
            <button
            @click="triggerRequest"
              class="block text-left w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900"
              role="menuitem"
            >
              Trigger
            </button>
          </div>
          <div class="py-1" role="none">
            <button
              @click="deleteRequest"
              class="block text-left w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900"
              role="menuitem"
            >
              Delete
            </button>
          </div>
        </div>
      </div>
    </div>
  </li>
</template>

<script>
import moment from "moment";
import delay from "lodash/delay";

export default {
  props: ["plan"],
  data() {
    return {
      show: false,
      copying: false,
    };
  },
  methods: {
    editRequest() {
      this.$emit("editRequest", this.plan);
      this.show = false;
    },
    onCopy() {
      this.copying = true;

      delay(([self, index]) => (self.copying = false), 300, [this]);
    },
    relativeTime(utcDatetime) {
      return moment.utc(utcDatetime).local().fromNow();
    },
    deleteRequest() {
      this.$emit("deleteRequest", this.plan.id);
      this.show = false;
    },
    triggerRequest(){
      this.$emit("triggerRequest", this.plan.id);
      this.show = false;
    }
  },
};
</script>

<style scoped>
</style>
