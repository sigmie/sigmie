<template>
  <div class="pb-5 border-b border-gray-200 space-y-3 sm:space-y-4 sm:pb-0">
    <div>
      <div class="sm:hidden">
        <select
          aria-label="Selected tab"
          v-model="selected"
          class="form-select block w-full pl-3 pr-10 py-2 text-base leading-6 border-gray-300 focus:outline-none focus:shadow-outline-blue focus:border-blue-300 sm:text-sm sm:leading-5 transition ease-in-out duration-150"
          @change="onSelectChange"
        >
          <option v-for="item in items" :value="item.key" :key="item.key">{{ item.name }}</option>
        </select>
      </div>

      <div class="hidden sm:block">
        <nav class="-mb-px flex space-x-8">
          <a
            v-for="item in items"
            :key="item.key"
            @click.prevent="replace(item.key)"
            :href="$route('account.settings',{section:item.key, project_id: $page.project_id})"
            :class="(item.key === section)?'text-orange-500 border-orange-400':'focus:text-gray-700 focus:border-gray-300 text-gray-500 hover:text-gray-700 hover:border-gray-300'"
            class="whitespace-no-wrap pb-4 px-1 border-b-2 border-transparent font-medium text-sm leading-5 focus:outline-none"
          >{{ item.name }}</a>
        </nav>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  methods: {
    onSelectChange() {
      this.replace(this.selected);
    },
    replace(value) {
      this.$inertia.replace(
        this.$route("account.settings", {
          section: value,
          project_id: this.$page.project_id,
        }),
        {
          method: "get",
          data: {},
          preserveState: true,
          preserveScroll: false,
          only: [],
        }
      );
    },
  },
  props: ["section"],
  data() {
    return {
      selected: this.section,
      items: [
        {
          name: "Account",
          key: "account",
        },
        {
          name: "Subscription",
          key: "subscription",
        },
      ],
    };
  },
};
</script>

<style scoped>
</style>