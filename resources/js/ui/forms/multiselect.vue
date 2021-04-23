<template>
  <div class="w-full">
    <label
      :for="name"
      class="block text-sm font-medium leading-5 text-gray-700"
    >
      {{ label }}
    </label>
    <div class="flex flex-col items-center relative">
      <div class="w-full svelte-1l8159u">
        <div
          class="my-2 p-1 flex border border-gray-300 bg-white shadow-sm rounded-md"
        >
          <div class="flex flex-auto flex-wrap">
            <div
              v-for="(item, index) in selectedValues"
              :key="index"
              class="flex justify-center items-center m-1 font-medium py-1 px-2 bg-white rounded-full text-teal-700 bg-teal-100 border border-teal-300"
            >
              <div
                class="text-xs font-normal leading-none max-w-full flex-initial"
              >
                {{ item[displayKey] }}
              </div>
                <icon-x class="w-3 h-3"></icon-x>
            </div>
            <div class="flex-1">
              <input
                placeholder=""
                class="bg-transparent p-1 px-2 appearance-none outline-none h-full w-full text-gray-800"
              />
            </div>
          </div>
          <div class="text-gray-300 w-8 py-1 pl-2 pr-1 flex items-center">
            <button
              class="cursor-pointer w-6 h-6 text-gray-600 outline-none focus:outline-none"
            >
              <icon-cheveron-down class="h-4 w-4"></icon-cheveron-down>
            </button>
          </div>
        </div>
      </div>

      <ul
        class="absolute mt-14 w-full bg-white shadow-lg max-h-56 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto focus:outline-none sm:text-sm"
        tabindex="-1"
        role="listbox"
        aria-labelledby="listbox-label"
        aria-activedescendant="listbox-option-3"
      >
        <!--
        Select option, manage highlight styles based on mouseenter/mouseleave and keyboard navigation.

        Highlighted: "text-white bg-indigo-600", Not Highlighted: "text-gray-900"
      -->
        <li
          v-for="(item, index) in items"
          :key="index"
          class="text-gray-900 cursor-default select-none relative py-2 pl-3 pr-9"
          id="listbox-option-0"
          role="option"
          @click="() => select(item)"
        >
          <div class="flex items-center">
            <!-- Selected: "font-semibold", Not Selected: "font-normal" -->
            <span class="font-normal block truncate">
              {{ item[displayKey] }}
            </span>
          </div>

          <!--
          Checkmark, only display for selected option.

          Highlighted: "text-white", Not Highlighted: "text-indigo-600"
        -->
          <span
            class="text-gray-600 absolute inset-y-0 right-0 flex items-center pr-4"
          >
            <icon-check class="h-5 w-5"></icon-check>
          </span>
        </li>

        <!-- More items... -->
      </ul>
    </div>
  </div>
</template>

<script>
export default {
  props: {
    value: {
      default: "",
    },
    displayKey: {
      default: "name",
    },
    items: {
      default: "",
    },
    selected: {
      default: "",
    },
    "aria-label": {
      default: "",
    },
    label: {
      default: "",
    },
    name: {
      default: "",
    },
    id: {
      default: "",
    },
    errors: {
      default: null,
    },
    validations: {
      default() {
        return {
          $anyError: false,
        };
      },
    },
    required: {
      default: "",
    },
  },
  data() {
    return {
      selectedValues: [],
    };
  },
  methods: {
    select(item) {
      this.selectedValues = [...this.selectedValues, item];
    },
  },
};
</script>

<style>
.top-100 {
  top: 100%;
}
.bottom-100 {
  bottom: 100%;
}
.max-h-select {
  max-height: 300px;
}
</style>
