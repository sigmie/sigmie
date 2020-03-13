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
          <div class="origin-top-right absolute right-0 mt-2 w-96 rounded-md shadow-lg">
            <div class="py-1 rounded-md bg-white shadow-xs" v-away="() => this.dropdown = 'closed'">
              <ul>
                <li>
                  <a
                    href="#"
                    class="block hover:bg-gray-50 focus:outline-none focus:bg-gray-50 transition duration-150 ease-in-out"
                  >
                    <div class="px-4 py-4 sm:px-6">
                      <div class="flex items-center justify-between">
                        <div
                          class="text-sm leading-5 font-medium text-orange-600 truncate"
                        >Subscribed</div>
                        <div class="ml-2 flex-shrink-0 flex">
                          <span
                            class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-green-800"
                          >#project_name</span>
                        </div>
                      </div>
                      <div class="mt-2 sm:flex sm:justify-between">
                        <div class="sm:flex">
                          <div
                            class="mr-6 flex items-center text-sm leading-5 text-gray-500"
                          >Lorem, ipsum dolor sit amet cons.</div>
                        </div>
                        <div class="mt-2 flex items-center text-sm leading-5 text-gray-500 sm:mt-0">
                          <span class="w-12">
                            <time datetime="2020-01-07">3 days</time>
                          </span>
                        </div>
                      </div>
                    </div>
                  </a>
                </li>
                <li class="border-t border-gray-200">
                  <a
                    href="#"
                    class="block hover:bg-gray-50 focus:outline-none focus:bg-gray-50 transition duration-150 ease-in-out"
                  >
                    <div class="px-4 py-4 sm:px-6">
                      <div class="flex items-center justify-between">
                        <div
                          class="text-sm leading-5 font-medium text-indigo-600 truncate"
                        >Back End Developer</div>
                        <div class="ml-2 flex-shrink-0 flex">
                          <span
                            class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800"
                          >Full-time</span>
                        </div>
                      </div>
                      <div class="mt-2 sm:flex sm:justify-between">
                        <div class="sm:flex">
                          <div class="mr-6 flex items-center text-sm leading-5 text-gray-500">
                            <svg
                              class="flex-shrink-0 mr-1.5 h-5 w-5 text-gray-400"
                              fill="currentColor"
                              viewBox="0 0 20 20"
                            >
                              <path
                                d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"
                              />
                            </svg>
                            Engineering
                          </div>
                          <div
                            class="mt-2 flex items-center text-sm leading-5 text-gray-500 sm:mt-0"
                          >
                            <svg
                              class="flex-shrink-0 mr-1.5 h-5 w-5 text-gray-400"
                              fill="currentColor"
                              viewBox="0 0 20 20"
                            >
                              <path
                                fill-rule="evenodd"
                                d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z"
                                clip-rule="evenodd"
                              />
                            </svg>
                            Remote
                          </div>
                        </div>
                        <div class="mt-2 flex items-center text-sm leading-5 text-gray-500 sm:mt-0">
                          <svg
                            class="flex-shrink-0 mr-1.5 h-5 w-5 text-gray-400"
                            fill="currentColor"
                            viewBox="0 0 20 20"
                          >
                            <path
                              fill-rule="evenodd"
                              d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z"
                              clip-rule="evenodd"
                            />
                          </svg>
                          <span>
                            Closing on
                            <time datetime="2020-01-07">January 7, 2020</time>
                          </span>
                        </div>
                      </div>
                    </div>
                  </a>
                </li>
                <li class="border-t border-gray-200">
                  <a
                    href="#"
                    class="block hover:bg-gray-50 focus:outline-none focus:bg-gray-50 transition duration-150 ease-in-out"
                  >
                    <div class="px-4 py-4 sm:px-6">
                      <div class="flex items-center justify-between">
                        <div
                          class="text-sm leading-5 font-medium text-indigo-600 truncate"
                        >Back End Developer</div>
                        <div class="ml-2 flex-shrink-0 flex">
                          <span
                            class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800"
                          >Full-time</span>
                        </div>
                      </div>
                      <div class="mt-2 sm:flex sm:justify-between">
                        <div class="sm:flex">
                          <div class="mr-6 flex items-center text-sm leading-5 text-gray-500">
                            <svg
                              class="flex-shrink-0 mr-1.5 h-5 w-5 text-gray-400"
                              fill="currentColor"
                              viewBox="0 0 20 20"
                            >
                              <path
                                d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"
                              />
                            </svg>
                            Engineering
                          </div>
                          <div
                            class="mt-2 flex items-center text-sm leading-5 text-gray-500 sm:mt-0"
                          >
                            <svg
                              class="flex-shrink-0 mr-1.5 h-5 w-5 text-gray-400"
                              fill="currentColor"
                              viewBox="0 0 20 20"
                            >
                              <path
                                fill-rule="evenodd"
                                d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z"
                                clip-rule="evenodd"
                              />
                            </svg>
                            Remote
                          </div>
                        </div>
                        <div class="mt-2 flex items-center text-sm leading-5 text-gray-500 sm:mt-0">
                          <svg
                            class="flex-shrink-0 mr-1.5 h-5 w-5 text-gray-400"
                            fill="currentColor"
                            viewBox="0 0 20 20"
                          >
                            <path
                              fill-rule="evenodd"
                              d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z"
                              clip-rule="evenodd"
                            />
                          </svg>
                          <span>
                            Closing on
                            <time datetime="2020-01-07">January 7, 2020</time>
                          </span>
                        </div>
                      </div>
                    </div>
                  </a>
                </li>
              </ul>
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
