<template>
  <div>
    <label :for="name" class="block text-sm font-medium leading-5 text-gray-700">{{ label }}</label>
    <div class="mt-1 rounded-md shadow-sm">
      <input
        :id="id"
        :type="type"
        :placeholder="placeholder"
        :class="{ 'border-red-700 border': error.length > 0 }"
        :autocomplete="autocomplete"
        :value="value"
        :name="name"
        :required="required"
        @input="$emit('input', $event.target.value)"
        @blur="$emit('blur', $event.target.value)"
        @focus="$emit('touch', $event.target.value)"
        @change="$emit('change', $event.target.value)"
        class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md placeholder-gray-400 focus:outline-none focus:border-blue-300 transition duration-150 ease-in-out sm:text-sm sm:leading-5"
      />
      <span v-if="error.length > 0" class="text-red-600 font-normal" role="alert">{{ error }}</span>
    </div>
  </div>
</template>

<script>
export default {
  props: {
    name: {
      default: ""
    },
    valid: {
      default: null
    },
    blur: {
      default: () => {}
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
    type: {
      default: ""
    },
    error: {
      default: ""
    },
    type: {
      default: ""
    },
    autocomplete: {
      default: ""
    },
    id: {
      default: ""
    },
    required: {
      default: true
    }
  },
  data() {
    return {};
  },
  mounted() {},
  methods: {}
};
</script>
