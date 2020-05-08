<template>
  <div class>
    <label
      :for="id"
      class="block text-sm font-medium leading-5 text-gray-700 sm:mt-px sm:pt-2"
    >{{ label }}</label>
    <div class="md:mt-2 sm:col-span-2">
      <div class="max-w-lg flex rounded-md shadow-sm">
        <textarea
          :id="id"
          :rows="rows"
          :required="required"
          :class="[validations.$anyError ? 'border-red-300 text-red-900 placeholder-red-300 focus:border-red-300 focus:text-' : '']"
          :placeholder="placeholder"
          :value="value"
          @input="$emit('input', $event.target.value)"
          @blur="$emit('blur', $event.target.value)"
          @focus="$emit('touch', $event.target.value)"
          @change="$emit('change', $event.target.value)"
          class="form-textarea block w-full transition duration-150 ease-in-out sm:text-sm sm:leading-5"
        ></textarea>
      </div>
      <slot name="info"></slot>
      <p v-if="info.length > 0" slot="info" class="mt-2 text-sm text-gray-500">{{ info }}</p>
      <div v-for="(message, rule) in errorMessages" :key="rule">
        <p
          v-if="!validations[rule] && validations.$dirty"
          class="mt-2 text-sm text-red-600"
        >{{ message }}</p>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  props: {
    name: {
      default: ""
    },
    validations: {
      default: () => {
        return {
          $anyError: false
        };
      }
    },
    blur: {
      default: () => {}
    },
    info: {
      default: ""
    },
    value: {
      default: ""
    },
    placeholder: {
      default: ""
    },
    label: {
      default: ""
    },
    old: {
      default: ""
    },
    error: {
      default: ""
    },
    id: {
      default: ""
    },
    required: {
      default: true
    },
    errorMessages: {
      default: () => {}
    }
  }
};
</script>

<style>
</style>
