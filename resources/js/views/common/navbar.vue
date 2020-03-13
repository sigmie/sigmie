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
            class="p-1 rounded-full hover:bg-gray-100 hover:text-gray-500 focus:outline-none focus:shadow-outline focus:text-gray-500"
            :class="[false ? 'text-gray-400 ' : 'bg-gray-50 text-gray-500']"
          >
            <icon-bell class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24"></icon-bell>
          </button>
          <div
            class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg"
          >
            <div class="py-1 rounded-md bg-white shadow-xs" v-away="() => this.dropdown = 'closed'">
              <div class="border-t border-gray-100"></div>
              <a
                href="#"
                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition ease-in-out duration-150"
              >Zooroyal</a>
              <a
                href="#"
                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition ease-in-out duration-150"
                :class="[true ? 'bg-gray-100': '']"
              >Weinfreunde</a>
              <a
                onclick
                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition ease-in-out duration-150 cursor-pointer"
              >Penny</a>
              <div class="border-t border-gray-100"></div>
              <a
                href="#"
                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition ease-in-out duration-150"
              >Profile</a>
              <a
                href="#"
                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition ease-in-out duration-150"
              >Settings</a>
              <a
                @click.prevent="logout"
                onclick
                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition ease-in-out duration-150 cursor-pointer"
              >
                Sign
                out
              </a>
            </div>
          </div>
        </div>

        <div class="ml-3 relative">
          <button
            class="max-w-xs flex items-center text-sm rounded-full focus:outline-none focus:shadow-outline"
            @click="dropdown = 'open'"
          >
            <img class="h-8 w-8 rounded-full" :src="avatarUrl" />
          </button>
          <div
            v-if="dropdown == 'open'"
            class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg"
          >
            <div class="py-1 rounded-md bg-white shadow-xs" v-away="() => this.dropdown = 'closed'">
              <div class="border-t border-gray-100"></div>
              <a
                href="#"
                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition ease-in-out duration-150"
              >Zooroyal</a>
              <a
                href="#"
                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition ease-in-out duration-150"
                :class="[true ? 'bg-gray-100': '']"
              >Weinfreunde</a>
              <a
                onclick
                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition ease-in-out duration-150 cursor-pointer"
              >Penny</a>
              <div class="border-t border-gray-100"></div>
              <a
                href="#"
                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition ease-in-out duration-150"
              >Profile</a>
              <a
                href="#"
                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition ease-in-out duration-150"
              >Settings</a>
              <a
                @click.prevent="logout"
                onclick
                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition ease-in-out duration-150 cursor-pointer"
              >
                Sign
                out
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  props: ["avatarUrl", "userId"],
  data() {
    return {
      sidebar: "closed",
      dropdown: "closed",
      notifications: []
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
    listenOnNotificationChannel() {
      this.$socket
        .private(`App.User.${this.userId}`)
        .notification(notification => {
          console.log(notification);

          this.addNotifications([notification.payload]);
        });
    },
    addNotifications(notifications) {
      this.notifications = this.notifications.concat(notifications);
    },
    logout() {
      document.getElementById("logout-form").submit();
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
