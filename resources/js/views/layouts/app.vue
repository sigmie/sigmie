<template>
  <layout :title="title">
    <div class="h-screen flex overflow-hidden bg-gray-100">
      <sidebar ref="sidebar"></sidebar>

      <div class="flex flex-col w-0 flex-1 overflow-hidden">
        <navbar v-cloak :user-id="$page.user.id" :avatar-url="$page.user.avatar_url"></navbar>

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

export default {
  components: {
    sidebar: require("../common/sidebar/sidebar").default,
    navbar: require("../common/navbar/navbar").default,
    Layout,
  },
  props: ["user", "project", "title"],
};
</script>

<style scoped>
</style>
