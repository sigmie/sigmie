<template>
  <div>
    <label :for="name" class="block text-sm font-medium leading-5 text-gray-700">
      {{ label }}
    </label>
    <div class="mt-2 rounded-md shadow-sm">
      <select
        :id="id"
        :name="name"
        :aria-label="label"
        :class="[
          validations.$anyError || errors
            ? 'border-red-300 text-red-900 placeholder-red-300 focus:border-red-300 focus:text-'
            : '',
        ]"
        :required="required"
        @input="$emit('input', $event.target.value)"
        @blur="$emit('blur', $event.target.value)"
        @focus="$emit('touch', $event.target.value)"
        @change="$emit('change', items[$event.target.value])"
        class="block focus:ring-indigo-500 w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
      >
        <option
          disabled
          value
          :selected="value === null"
        ></option>
        <option v-for="(item, index) in items" :value="index" :key="index"
        :selected="index === value"
        >
          {{ item[displayKey] }}
        </option>
      </select>
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
};
</script>

<style>
</style>
