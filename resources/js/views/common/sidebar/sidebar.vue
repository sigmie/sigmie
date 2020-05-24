<template>
  <div class="flex">
    <div class="hidden md:flex md:flex-shrink-0">
      <div class="flex flex-col w-64">
        <div class="flex items-center h-16 flex-shrink-0 px-4 bg-gray-900">
          <logo-white height="50px" width="159px" class="mx-auto py-1"></logo-white>
        </div>
        <div class="h-0 flex-1 flex flex-col overflow-y-auto">
          <nav class="flex-1 px-2 py-4 bg-gray-800">
            <inertia-link
              v-for="(item, index) in items"
              :key="index"
              :class="[isRoute(item.name) ? 'text-white bg-gray-900 focus:outline-none' : 'mt-1 text-gray-300 hover:text-white hover:bg-gray-700 focus:outline-none focus:text-white']"
              class="group flex items-center px-2 py-2 text-base leading-6 font-medium rounded-md focus:bg-gray-700 transition ease-in-out duration-150"
              :href="$route(item.name)"
            >
              <component
                :class="[ isRoute(item.name) ? 'text-gray-400': 'text-gray-300']"
                class="mr-4 h-6 w-6 group-hover:text-gray-300 group-focus:text-gray-300 transition ease-in-out duration-150"
                stroke="currentColor"
                fill="none"
                viewBox="0 0 24 24"
                :is="'icon-'+item.icon"
              ></component>
              {{ item.text }}
            </inertia-link>
          </nav>
        </div>
      </div>
    </div>
    <div class="md:hidden">
      <div
        @click="open"
        :class="{'opacity-75 pointer-events-auto': sidebar == 'open', 'opacity-0 pointer-events-none':sidebar == 'closed'}"
        class="fixed inset-0 z-30 bg-gray-600 opacity-0 pointer-events-none transition-opacity ease-linear duration-300"
      ></div>
      <div
        class="fixed inset-y-0 left-0 flex flex-col z-40 max-w-xs w-full bg-gray-800 transform ease-in-out duration-300"
        :class="{'translate-x-0': sidebar == 'open', '-translate-x-full': sidebar == 'closed'}"
      >
        <div class="absolute top-0 right-0 -mr-14 p-1">
          <button
            v-show="sidebar == 'open'"
            @click="sidebar = 'closed'"
            class="flex items-center justify-center h-12 w-12 rounded-full focus:outline-none focus:bg-gray-600"
          >
            <svg class="h-6 w-6 text-white" stroke="currentColor" fill="none" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M6 18L18 6M6 6l12 12"
              />
            </svg>
          </button>
        </div>
        <div class="flex-shrink-0 flex items-center h-16 px-4 bg-gray-900">
          <logo-white height="50px" width="159px" class="mx-auto py-1"></logo-white>
        </div>
        <div class="flex-1 h-0 overflow-y-auto">
          <nav class="px-2 py-4">
            <inertia-link
              v-for="(item, index) in items"
              :key="index"
              :class="[isRoute(item.name) ? 'text-white bg-gray-900 focus:outline-none' : 'mt-1 text-gray-300 hover:text-white hover:bg-gray-700 focus:outline-none focus:text-white']"
              class="group flex items-center px-2 py-2 text-base leading-6 font-medium rounded-md focus:bg-gray-700 transition ease-in-out duration-150"
              :href="$route(item.name)"
            >
              <component
                :class="[ isRoute(item.name) ? 'text-gray-400': 'text-gray-300']"
                class="mr-4 h-6 w-6 group-hover:text-gray-300 group-focus:text-gray-300 transition ease-in-out duration-150"
                stroke="currentColor"
                fill="none"
                viewBox="0 0 24 24"
                :is="'icon-'+item.icon"
              ></component>
              {{ item.text }}
            </inertia-link>
          </nav>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {
      sidebar: "closed",
      path: "",
      items: [
        {
          text: "Dashboard",
          name: "dashboard",
          icon: "home"
        },
        {
          text: "Bar",
          name: "bar",
          icon: "team"
        }
      ]
    };
  },
  methods: {
    isRoute(name) {
      return route().current(name);
    },
    close() {
      this.sidebar = "closed";
    },
    open() {
      this.sidebar = "open";
    }
  }
};
</script>

<style>
</style>
