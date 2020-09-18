<template>
  <div
    v-on-clickaway="()=> $emit('away')"
    class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg"
  >
    <div class="py-1 rounded-md bg-white shadow-xs">
      <p class="text-xs text-gray-300 font-semibold pl-4 pt-2 pb-1">PROJECTS</p>
      <inertia-link
        href
        @click.prevent
        v-if="!$page.projects || $page.projects.length === 0"
        :disabled="true"
        class="block px-4 py-2 text-sm text-gray-300 cursor-default"
      >- no project -</inertia-link>
      <inertia-link
        v-for="(project, index) in $page.projects"
        :key="index"
        :href="$route('dashboard',{project: project.id})"
        class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition ease-in-out duration-150"
      >{{ project.name }}</inertia-link>
      <div class="border-t border-gray-100"></div>
      <inertia-link
        :href="$route('account.settings')"
        class="text-xs text-gray-300 font-semibold pl-4 pt-2 pb-1"
      >SETTINGS</inertia-link>
      <inertia-link
        :href="$route('account.settings',{ section:'account',project_id: $page.project_id })"
        class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition ease-in-out duration-150"
      >Account</inertia-link>
      <!-- <inertia-link
        :href="$route('account.settings',{ section:'general' })"
        class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition ease-in-out duration-150"
      >General</inertia-link>-->
      <inertia-link
        :href="$route('account.settings',{ section:'subscription',project_id: $page.project_id })"
        class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition ease-in-out duration-150"
      >Subscritpion</inertia-link>

      <!-- <inertia-link
        :href="$route('account.settings',{ section:'notifications' })"
        class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition ease-in-out duration-150"
      >Notifications</inertia-link>-->

      <a
        href="https://docs.sigmie.com/app"
        target="_blank"
        class="flex flex-row px-4 py-2 border-t border-gray-100 text-sm text-gray-700 hover:bg-gray-100 transition ease-in-out duration-150 cursor-pointer"
      >
        <span>Documentation</span>
      </a>

      <a
        href="mailto:nico@sigmie.com?subject=I%20need%20your%20help&body=Hey%20Nico!%0D%0A%0D%0ACloud%20you%20help%20me%20with "
        class="flex flex-row px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition ease-in-out duration-150 cursor-pointer"
      >
        <span>Support</span>
      </a>
      <a
        @click.prevent="logout"
        class="flex flex-row px-4 py-2 border-t border-gray-100 text-sm text-gray-700 hover:bg-gray-100 transition ease-in-out duration-150 cursor-pointer"
      >
        <svg
          viewBox="0 0 20 20"
          fill="currentColor"
          class="logout h-4 text-gray-500 mr-1 self-center"
        >
          <path
            fill-rule="evenodd"
            d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z"
            clip-rule="evenodd"
          />
        </svg>
        <span>Log out</span>
      </a>
    </div>
  </div>
</template>

<script>
export default {
  methods: {
    async logout() {
      await this.$http.post(this.$route("logout"), {});

      window.location = this.$route("login");
    },
  },
};
</script>

<style>
</style>
