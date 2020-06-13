<template>
  <div class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg">
    <div class="py-1 rounded-md bg-white shadow-xs">
      <p class="text-xs text-gray-300 font-semibold pl-4 pt-2 pb-1">PROJECTS</p>
      <inertia-link
        v-for="(project, index) in $page.projects"
        :key="index"
        :href="$route('dashboard',{project: project.id})"
        class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition ease-in-out duration-150"
      >{{ project.name }}</inertia-link>
      <div class="border-t border-gray-100"></div>
      <p class="text-xs text-gray-300 font-semibold pl-4 pt-2 pb-1">SETTINGS</p>
      <a
        href="#"
        class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition ease-in-out duration-150"
      >Profile</a>
      <a
        href="#"
        class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition ease-in-out duration-150"
      >Tokens</a>
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
</template>

<script>
export default {
  methods: {
    logout() {
      this.$inertia.post(this.$route("logout"), {});
    }
  }
};
</script>

<style>
</style>
