<template>
  <nav class="flex flex-col px-2 pb-4 h-1/5">
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
        $page.props.project_id === null
          ? $route('dashboard')
          : $route(item.name, item.routeParams)
      "
    >
      <component class="mr-4 h-6 w-6" :is="'icon-' + item.icon"></component>
      {{ item.text }}
      <span
        v-if="item.badge"
        :class="
          item.badge.color === 'blue' ? 'bg-blue-100 text-blue-800' : null
        "
        class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium leading-4"
        >{{ item.badge.text }}</span
      >
    </inertia-link>
  </nav>
</template>

<script>
export default {
  data() {
    return {
      disabled: false,
      items: [
        {
          enabled: true,
          text: "Notifications",
          name: "settings",
          routeParams: [],
          icon: "chat",
        },
        {
          enabled: true,
          text: "Settings",
          name: "settings",
          routeParams: {
            project:
              this.$page.props.project_id === null
                ? ""
                : this.$page.props.project_id,
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
  },
};
</script>

<style scoped>
</style>

