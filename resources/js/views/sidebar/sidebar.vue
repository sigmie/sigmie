<template>
  <div class="flex">
    <div class="hidden md:flex md:flex-shrink-0">
      <div class="flex flex-col w-64">
        <div class="flex items-center h-16 flex-shrink-0 px-4 bg-gray-900">
          <inertia-link class="mx-auto py-1" :href="$route('dashboard')">
            <logo-white height="50px" width="159px"></logo-white>
          </inertia-link>
        </div>

        <div class="h-0 flex-1 flex flex-col overflow-y-auto bg-gray-800">
          <info
            :project-name="$page.project_name"
            :cluster-url="$page.project_cluster_url"
            class="pt-7 pb-5"
          ></info>

          <nav class="flex-1 px-2 py-4">
            <inertia-link
              v-for="(item, index) in items"
              :key="index"
              :class="[
                isRoute(item.name)
                  ? 'text-white bg-gray-900 focus:outline-none'
                  : 'mt-1 text-gray-300 hover:text-white hover:bg-gray-700 focus:outline-none focus:text-white',
                disabled ? 'pointer-events-none' : '',
              ]"
              class="group mx-1 my-2 flex items-center px-2 py-2 text-base leading-6 font-medium rounded-md focus:bg-gray-700 transition ease-in-out duration-150"
              :href="
                $page.project_id === null
                  ? '#'
                  : $route(item.name, item.routeParams)
              "
            >
              <component
                class="mr-4 h-6 w-6"
                :is="'icon-' + item.icon"
              ></component>
              {{ item.text }}
              <span
                v-if="item.badge"
                :class="
                  item.badge.color === 'blue'
                    ? 'bg-blue-100 text-blue-800'
                    : null
                "
                class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium leading-4"
                >{{ item.badge.text }}</span
              >
            </inertia-link>
          </nav>
        </div>
      </div>
    </div>
    <div class="md:hidden">
      <div
        @click="closeSidebarRequest"
        :class="{
          'opacity-75 pointer-events-auto': sidebarState == 'open',
          'opacity-0 pointer-events-none': sidebarState == 'closed',
        }"
        class="fixed inset-0 z-30 bg-gray-600 opacity-0 pointer-events-none transition-opacity ease-linear duration-300"
      ></div>
      <div
        class="fixed inset-y-0 left-0 flex flex-col z-40 max-w-xs w-full bg-gray-800 transform ease-in-out duration-300"
        :class="{
          'translate-x-0': sidebarState === 'open',
          '-translate-x-full': sidebarState === 'closed',
        }"
      >
        <div class="absolute top-0 right-0 -mr-14 p-1">
          <button
            v-show="sidebarState === 'open'"
            @click="closeSidebarRequest"
            class="flex items-center justify-center h-12 w-12 rounded-full focus:outline-none focus:bg-gray-600"
          >
            <svg
              class="h-6 w-6 text-white"
              stroke="currentColor"
              fill="none"
              viewBox="0 0 24 24"
            >
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
          <logo-white
            height="50px"
            width="159px"
            class="mx-auto py-1"
          ></logo-white>
        </div>
        <div class="flex-1 h-0 overflow-y-auto">
          <info
            :project-name="$page.project_name"
            :cluster-url="$page.project_cluster_url"
            class="px-2 py-4"
          ></info>

          <nav class="px-2 py-4">
            <inertia-link
              v-for="(item, index) in items"
              :key="index"
              :class="[
                isRoute(item.name)
                  ? 'text-white bg-gray-900 focus:outline-none'
                  : 'mt-1 text-gray-300 hover:text-white hover:bg-gray-700 focus:outline-none focus:text-white',
                disabled ? 'pointer-events-none' : '',
              ]"
              class="group mx-2 my-4 flex items-center px-2 py-2 text-base leading-6 font-medium rounded-md focus:bg-gray-700 transition ease-in-out duration-150"
              :href="
                $page.project_id === null
                  ? '#'
                  : $route(item.name, item.routeParams)
              "
            >
              <component
                class="mr-4 h-6 w-6"
                :is="'icon-' + item.icon"
              ></component>
              {{ item.text }}
              <span
                v-if="item.badge"
                :class="
                  item.badge.color === 'blue'
                    ? 'bg-blue-100 text-blue-800'
                    : null
                "
                class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium leading-4"
                >{{ item.badge.text }}</span
              >
            </inertia-link>
          </nav>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import info from "./_info";
import isUndefined from "lodash/isUndefined";

export default {
  props: ["sidebarState", "disabled"],
  components: {
    info,
  },
  data() {
    return {
      path: "",
      items: [
        {
          text: "Dashboard",
          name: "dashboard",
          routeParams: [],
          icon: "home",
        },
        {
          text: "API tokens",
          name: "token.index",
          routeParams: [],
          icon: "key",
          badge: {
            text: "Beta",
            color: "blue",
          },
        },
        {
          text: "Settings",
          name: "settings",
          routeParams: {
            project:
              this.$page.project_id === null ? "" : this.$page.project_id,
          },
          icon: "cog",
        },
      ],
    };
  },
  methods: {
    isRoute(name) {
      return this.$route().current(name);
    },
    closeSidebarRequest() {
      this.$emit("closeSidebarRequest");
    },
  },
};
</script>

<style>
</style>
