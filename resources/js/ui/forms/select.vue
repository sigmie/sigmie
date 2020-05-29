<template>
  <fieldset>
    <legend class="block text-sm font-medium leading-5 text-gray-700">{{ label }}</legend>
    <div class="mt-1 rounded-md shadow-sm">
      <div>
        <select
          :id="id"
          :name="name"
          :aria-label="label"
          :class="[validations.$anyError ? 'border-red-300 text-red-900 placeholder-red-300 focus:border-red-300 focus:text-' : '']"
          :required="required"
          :value="value"
          @input="$emit('input', $event.target.value)"
          @blur="$emit('blur', $event.target.value)"
          @focus="$emit('touch', $event.target.value)"
          @change="$emit('change', $event.target.value)"
          class="form-select relative block w-full rounded-none rounded-md bg-transparent focus:z-10 transition ease-in-out duration-150 sm:text-sm sm:leading-5"
        >
          <option v-for="(item, index) in items" :value="index" :key="index">{{ item }}</option>
        </select>
      </div>
    </div>
  </fieldset>
</template>

<script>
export default {
  props: {
    value: {
      default: ""
    },
    items: {
      default: ""
    },
    selected: {
      default: ""
    },
    "aria-label": {
      default: ""
    },
    label: {
      default: ""
    },
    name: {
      default: ""
    },
    id: {
      default: ""
    },
    validations: {
      default() {
        return {
          $anyError: false
        };
      }
    },
    required: {
      default: ""
    }
  }
};
</script>

<style>
</style>
