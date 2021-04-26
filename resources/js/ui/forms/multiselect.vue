<template>
  <div class="w-full" v-on-clickaway="closeDropdown">
    <label
      :for="name"
      class="block text-sm font-medium leading-5 text-gray-700"
    >
      {{ label }}
    </label>
    <div class="flex flex-col items-center relative">
      <div class="w-full svelte-1l8159u">
        <div
          tabindex="0"
          @keydown="handleInput"
          @focus="openDropdown"
          @blur="closeDropdown"
          class="focus:ring-indigo-500 my-2 p-1 flex border border-gray-300 bg-white shadow-sm rounded-md"
        >
          <div class="flex flex-auto flex-wrap">
            <div
              v-for="(item, index) in selectedValues"
              :key="index"
              @click="() => deselect(item)"
              :class="isSelectedForRemoval(item) ? 'bg-gray-100' : ''"
              class="flex cursor-pointer justify-center items-center m-1 font-medium py-1 px-2 bg-white rounded-full text-teal-700 bg-teal-100 border border-teal-300"
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
                readonly
                tabindex="-1"
                @keydown="handleInput"
                @focus="openDropdown"
                @blur="closeDropdown"
                placeholder=""
                class="bg-transparent p-1 px-2 appearance-none outline-none h-full w-full text-gray-800"
              />
            </div>
          </div>
          <div class="text-gray-300 w-8 py-1 pl-2 pr-1 flex items-center">
            <button
              class="cursor-pointer w-6 h-6 text-gray-600 outline-none focus:outline-none"
            >
              <icon-cheveron-up
                v-if="showDropdown"
                @click="closeDropdown"
                class="h-4 w-4"
              ></icon-cheveron-up>
              <icon-cheveron-down
                @click="closeDropdown"
                v-else
                class="h-4 w-4"
              ></icon-cheveron-down>
            </button>
          </div>
        </div>
      </div>

      <ul
        v-if="showDropdown"
        class="absolute mt-14 w-full bg-white shadow-lg max-h-56 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto focus:outline-none sm:text-sm"
        tabindex="-1"
        role="listbox"
        aria-labelledby="listbox-label"
        aria-activedescendant="listbox-option-3"
      >
        <li
          v-for="(item, index) in items"
          :key="index"
          :class="isFocused(item) ? 'bg-gray-100' : ''"
          class="text-gray-900 cursor-default select-none relative py-2 pl-3 pr-9 hover:bg-gray-100"
          id="listbox-option-0"
          role="option"
          @click="() => (isSelected(item) ? deselect(item) : select(item))"
        >
          <div class="flex items-center">
            <span
              :class="isSelected(item) ? 'font-semibold' : ''"
              class="font-normal block truncate"
            >
              {{ item[displayKey] }}
            </span>
          </div>

          <span
            v-if="isSelected(item)"
            class="text-gray-600 absolute inset-y-0 right-0 flex items-center pr-4"
          >
            <icon-check class="h-5 w-5"></icon-check>
          </span>
        </li>
      </ul>
    </div>
  </div>
</template>

<script>
import has from "lodash/has";
import forEach from "lodash/forEach";
import isNull from "lodash/isNull";
import { mixin as clickaway } from "vue-clickaway2";

export default {
  mixins: [clickaway],
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
      selectedValues: {},
      showDropdown: false,
      selectedForRemove: null,
      focused: null,
    };
  },
  methods: {
    select(item) {
      if (this.isSelected(item)) {
        return;
      }

      this.$set(this.selectedValues, item.id, item);
    },
    deselect(item) {
      this.$delete(this.selectedValues, item.id, item);
      this.selectedForRemove = null;
    },
    isSelected(item) {
      return has(this.selectedValues, item.id);
    },
    closeDropdown() {
      this.showDropdown = false;
    },
    openDropdown() {
      this.showDropdown = true;
    },
    handleInput(e) {
      if (e.key === "Backspace") {
        if (isNull(this.selectedForRemove)) {
          this.selectLastElementForRemoval();
          return;
        }

        this.deselect(this.selectedForRemove);
      }
      if (e.key === "ArrowDown" || e.key === "Down") {
        this.handleDown();
      }
      if (e.key === "ArrowUp" || e.key === "Up") {
        this.handleUp();
      }

      if (e.key === "Enter") {
        if (this.isSelected(this.focused)) {
          this.deselect(this.focused);
          return;
        }

        this.select(this.focused);
      }

      console.log(e.key);
    },
    handleDown() {
      this.focusNext();
    },
    handleUp() {
      this.focusPrevious();
    },
    focusNext() {
      // If nothing focused yet, focus the first element
      if (isNull(this.focused)) {
        this.focused = Object.values(this.items)[0];
        return;
      }

      let keys = Object.keys(this.items);
      let nextIndex = keys.indexOf(this.focused.id) + 1;

      // If it's the last element focus the first again
      if (nextIndex + 1 > keys.length) {
        this.focused = Object.values(this.items)[0];
        return;
      }

      let nextKey = keys[nextIndex];

      this.focused = this.items[nextKey];
    },
    focusPrevious() {
      // If nothing focused yet, focus the first element
      if (isNull(this.focused)) {
        this.focused = this.items[
          Object.keys(this.items)[Object.keys(this.items).length - 1]
        ];
        return;
      }

      let keys = Object.keys(this.items);
      let nextIndex = keys.indexOf(this.focused.id) - 1;

      // If it's the last element focus the first again
      if (nextIndex < 0) {
        this.focused = this.items[
          Object.keys(this.items)[Object.keys(this.items).length - 1]
        ];
        return;
      }

      let nextKey = keys[nextIndex];

      this.focused = this.items[nextKey];
    },
    isFocused(item) {
      if (isNull(this.focused)) {
        return false;
      }

      return this.focused.id === item.id;
    },
    selectLastElementForRemoval() {
      let values = this.selectedValues;
      if (Object.keys(values).length > 0) {
        let lastSelected =
          values[Object.keys(values)[Object.keys(values).length - 1]];

        this.selectForRemoval(lastSelected);
      }
    },
    selectForRemoval(item) {
      this.selectedForRemove = item;
    },
    deselectForRemoval(item) {
      this.selectedForRemove = null;
    },
    isSelectedForRemoval(item) {
      if (isNull(this.selectedForRemove)) {
        return false;
      }

      return this.selectedForRemove.id === item.id;
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
