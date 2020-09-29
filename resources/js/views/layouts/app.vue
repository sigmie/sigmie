<template>
  <layout :title="title">
    <div class="h-screen flex overflow-hidden bg-gray-100">
      <sidebar :disabled="sidebarDisabled" :sidebar-state="sidebarState" @closeSidebarRequest="closeSidebar"></sidebar>

      <div class="flex flex-col w-0 flex-1 overflow-hidden">
        <navbar
          v-cloak
          @openSidebarRequest="openSidebar"
          :user-id="$page.user.id"
          :avatar-url="$page.user.avatar_url"
        ></navbar>

        <main id="main" class="flex-1 relative overflow-y-auto py-6 focus:outline-none">
          <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
            <slot></slot>
          </div>
        </main>
      </div>
    </div>
  </layout>
</template>

<script>
import Layout from "./layout";

import sidebar from "../sidebar/sidebar"
import navbar from "../navbar/navbar"

export default {
  components: {
    sidebar,
    navbar,
    Layout
  },
  props: {
    name: {
      default: '',
    },
    project: {
      default: null,
    },
    title: {
      default: "Application",
    },
    sidebarDisabled: {
      default: false,
    },
  },
  data() {
    return {
      sidebarState: "closed",
    };
  },
  methods: {
    openSidebar() {
      this.sidebarState = "open";
    },
    closeSidebar() {
      this.sidebarState = "closed";
    },
  },
};
</script>

<style scoped>
</style>
