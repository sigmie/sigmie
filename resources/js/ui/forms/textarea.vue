<template>
  <div class>
    <label
      v-if="label.length > 0"
      :for="id"
      class="block text-sm font-medium leading-5 text-gray-700 sm:mt-px sm:pt-2 pb-1"
      >{{ label }}</label
    >
    <div class="mt-2">
      <div class="max-w-lg flex rounded-md shadow-sm">
        <textarea
          :id="id"
          :rows="rows"
          :required="required"
          :class="[
            validations.$anyError || errors
              ? 'border-red-300 text-red-900 placeholder-red-300 focus:border-red-300 focus:text-'
              : '',
          ]"
          :placeholder="placeholder"
          :disabled="disabled"
          :value="value"
          type="text"
          @input="$emit('input', $event.target.value)"
          @blur="$emit('blur', $event.target.value)"
          @focus="$emit('touch', $event.target.value)"
          @change="$emit('change', $event.target.value)"
          class="flex outline-none ring-0 flex-1 appearance-none w-full px-3 py-2 border border-gray-300 focus:text-gray-700 rounded-md placeholder-gray-400 focus:outline-none focus:border-blue-300 transition duration-150 ease-in-out sm:text-sm sm:leading-5"
        ></textarea>
      </div>
      <slot name="info"></slot>
      <p v-if="info.length > 0" slot="info" class="mt-2 text-sm text-gray-500">
        {{ info }}
      </p>

      <!-- Inertia form validation -->
      <div v-for="(message, index) in errors" :key="index">
        <p class="mt-2 text-sm text-red-600">
          {{ message }}
        </p>
      </div>

      <div v-for="(message, rule) in errorMessages" :key="rule">
        <p
          v-if="
            validations[rule] === false &&
            validations.$dirty &&
            validations.$pending === false
          "
          class="mt-2 text-sm text-red-600"
        >
          {{ message }}
        </p>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  props: {
    name: {
      default: "",
    },
    rows: {
      default: "5",
    },
    validations: {
      default: () => {
        return {
          $anyError: false,
        };
      },
    },
    blur: {
      default: () => {},
    },
    info: {
      default: "",
    },
    value: {
      default: "",
    },
    placeholder: {
      default: "",
    },
    label: {
      default: "",
    },
    old: {
      default: "",
    },
    error: {
      default: "",
    },
    disabled: {
      default: false,
    },
    id: {
      default: "",
    },
    required: {
      default:false,
    },
    errors: {
      default: null,
    },
    errorMessages: {
      default: () => {},
    },
  },
};
</script>

<style>
</style>
