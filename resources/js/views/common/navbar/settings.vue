<template>
  <div
    v-away="() => $emit('away')"
    class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg"
  >
    <div class="py-1 rounded-md bg-white shadow-xs">
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
</template>

<script>
export default {
  methods: {
    logout() {
      let _token = document.head.querySelector('meta[name="csrf-token"]')
        .content;
      this.$inertia.post(this.$route("logout"), { _token });
    }
  }
};
</script>

<style>
</style>
