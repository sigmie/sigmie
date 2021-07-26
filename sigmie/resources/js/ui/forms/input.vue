<template>
  <div>
    <label
      v-if="label.length > 0 && suffix.length === 0"
      :for="id"
      class="block text-sm font-medium leading-5 text-gray-700 pb-1"
      >{{ label }}</label
    >
    <div
      :class="label.length > 0 ?'mt-2':''"
      class="relative rounded-md shadow-sm"
      v-if="suffix.length === 0"
    >
      <input
        :id="id"
        :type="type"
        :placeholder="placeholder"
        :data-lpignore="lpignore"
        :class="[
          validations.$anyError || errors
            ? 'border-red-300 text-red-900 placeholder-red-300 focus:border-red-300'
            : '',
        ]"
        :autocomplete="autocomplete"
        :name="name"
        :required="required"
        :value="value"
        @input="$emit('input', $event.target.value)"
        @blur="$emit('blur', $event.target.value)"
        @focus="$emit('touch', $event.target.value)"
        @change="$emit('change', $event.target.value)"
        class="flex flex-1 appearance-none w-full px-3 py-2 border border-gray-300 focus:text-gray-700 rounded-md placeholder-gray-400 focus:outline-none focus:border-blue-300 transition duration-150 ease-in-out sm:text-sm sm:leading-5"
      />

      <div
        v-if="
          (validations.$anyError && validations.$pending === false) || errors
        "
        class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none"
      >
        <svg
          class="h-5 w-5 text-red-500"
          fill="currentColor"
          viewBox="0 0 20 20"
        >
          <path
            fill-rule="evenodd"
            d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
            clip-rule="evenodd"
          />
        </svg>
      </div>
    </div>

    <div class="col-span-3 sm:col-span-2" v-if="suffix.length > 0">
      <label
        :for="id"
        class="block text-sm font-medium leading-5 text-gray-700"
        >{{ label }}</label
      >
      <div class="mt-1 flex rounded-md shadow-sm">
        <input
          :id="id"
          :type="type"
          :placeholder="placeholder"
          :class="[
            validations.$anyError || errors
              ? 'border-red-300 text-red-900 placeholder-red-300 focus:border-red-300 focus:text-'
              : '',
          ]"
          :autocomplete="autocomplete"
          :name="name"
          :required="required"
          :value="value"
          @input="$emit('input', $event.target.value)"
          @blur="$emit('blur', $event.target.value)"
          @focus="$emit('touch', $event.target.value)"
          @change="$emit('change', $event.target.value)"
          class="flex-1 block w-full focus:ring-indigo-500 focus:border-indigo-500 min-w-0 rounded-none rounded-l-md sm:text-sm border-gray-300"
        />
        <span
          class="inline-flex items-center px-3 rounded-r-md border border-l-0 border-gray-300 bg-gray-50 text-gray-500 text-sm"
          >{{ suffix }}</span
        >
      </div>
    </div>

    <!-- Inertia form validation -->
    <div v-for="(message, index) in errors" :key="index">
      <p class="mt-2 text-sm text-red-600">
        {{ message }}
      </p>
    </div>

    <!-- Vuelidate validations -->
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
</template>

<script>
export default {
  props: {
    suffix: {
      default: "",
    },
    lpignore: {
      default: false,
    },
    name: {
      default: "",
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
    type: {
      default: "",
    },
    error: {
      default: "",
    },
    type: {
      default: "",
    },
    autocomplete: {
      default: "",
    },
    id: {
      default: "",
    },
    required: {
      default: false,
    },
    errorMessages: {
      default: () => {},
    },
    errors: {
      default: null,
    },
  },
  data() {
    return {};
  },
  mounted() {},
  methods: {},
};
</script>
