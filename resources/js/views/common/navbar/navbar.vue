<template>
  <div class="relative z-10 flex-shrink-0 flex h-16 bg-white shadow">
    <button
      @click="openSidebar"
      class="px-4 border-r border-gray-200 text-gray-500 focus:outline-none focus:bg-gray-100 focus:text-gray-600 md:hidden"
    >
      <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          stroke-width="2"
          d="M4 6h16M4 12h16M4 18h7"
        />
      </svg>
    </button>

    <div class="flex-1 px-4 flex justify-between">
      <div class="flex-1 flex">
        <div class="w-full flex md:ml-0">
          <label for="search_field" class="sr-only">Search</label>
          <div class="relative w-full text-gray-400 focus-within:text-gray-600">
            <div class="absolute inset-y-0 left-0 flex items-center pointer-events-none">
              <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                <path
                  fill-rule="evenodd"
                  clip-rule="evenodd"
                  d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z"
                />
              </svg>
            </div>
            <input
              id="search_field"
              class="block w-full h-full pl-8 pr-3 py-2 rounded-md text-gray-900 placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 sm:text-sm"
              placeholder="Search"
            />
          </div>
        </div>
      </div>
      <div class="ml-4 flex items-center md:ml-6">
        <div class="ml-3 relative">
          <button
            class="p-1 text-gray-400 rounded-full hover:bg-gray-100 hover:text-gray-500 focus:outline-none focus:shadow-outline focus:text-gray-500"
            @click="notifications = 'open'"
          >
            <badge
              class="absolute top-1 right-1 text-orange-400"
              stroke="currentColor"
              fill="currentColor"
              width="10px"
              height="10px"
              viewBox="0 0 12 12"
            ></badge>

            <icon-bell class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24"></icon-bell>
          </button>
          <div class="origin-top-right absolute right-0 mt-2 w-96 rounded-md shadow-lg" v-cloak>
            <notifications
              v-if="notifications === 'open' && notificationsData.length > 0"
              @away="closeNotifications"
              :notifications="notificationsData"
            ></notifications>
          </div>
        </div>

        <div class="ml-3 relative">
          <button
            class="max-w-xs flex items-center text-sm rounded-full focus:outline-none focus:shadow-outline"
            @click="settings = 'open'"
          >
            <img class="h-8 w-8 rounded-full" :src="avatarUrl" />
          </button>
          <settings v-if="settings == 'open'" @away="closeSettings"></settings>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import moment from 'moment';
export default {
  components: {
    notifications: require("./notifications").default,
    settings: require("./settings").default,
    badge: require("./badge").default
  },
  props: ["avatarUrl", "userId"],
  data() {
    return {
      sidebar: "closed",
      settings: "closed",
      notifications: "closed",
      notificationsData: []
    };
  },
  async beforeMount() {
    this.listenOnNotificationChannel();
    this.fetchNotifications();
  },
  methods: {
    async fetchNotifications() {
      const response = await this.$http.get("/notification");

      this.addNotifications(response.data);
    },
    closeSettings() {
      this.settings = "close";
    },
    closeNotifications() {
      this.notifications = "close";
    },
    listenOnNotificationChannel() {
      this.$socket
        .private(`App.User.${this.userId}`)
        .notification(notification => {
          this.addNotification(notification.id);
        });
    },
    async addNotification(id) {
      const response = await this.$http.get(`notification/${id}`);
      console.log(response.data);

      this.addNotifications(response.data);
    },
    addNotifications(notificationsData) {
      this.notificationsData = notificationsData.concat(this.notificationsData);
    },
    openSidebar() {
      this.$root.$refs.sidebar.open();
    },
    closeSidebar() {
      this.$root.$refs.sidebar.close();
    }
  }
};
</script>

<style>
</style>
