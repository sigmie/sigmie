<template>
  <div class="pb-5 border-b border-gray-200 space-y-3 sm:space-y-4 sm:pb-0">
    <div>
      <!-- Dropdown menu on small screens -->
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
            @click.prevent="onClick(item.key)"
            :href="$route('account.settings',{section:item.key})"
            :class="(item.key === selected)?'text-orange-500 border-orange-400':'focus:text-gray-700 focus:border-gray-300 text-gray-500 hover:text-gray-700 hover:border-gray-300'"
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
      this.pushState(this.selected);
      this.emitChange(this.selected);
    },
    pushState(section) {
      window.history.replaceState(
        {},
        window.title,
        this.$route("account.settings", { section: section })
      );
    },
    onClick(value) {
      this.selected = value;
      this.pushState(this.selected);
      this.emitChange(this.selected);
    },
    emitChange(newValue) {
      this.$emit("change", newValue);
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
          name: "General",
          key: "general",
        },
        {
          name: "Subscription",
          key: "subscription",
        },
        {
          name: "Notification",
          key: "notifications",
        },
      ],
    };
  },
};
</script>

<style scoped>
</style>