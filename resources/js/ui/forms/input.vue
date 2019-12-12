<template>
  <div class="box">
    <div class="mx-auto">
      <label :for="name" class="pb-1 block text-gray-600 font-normal text-sm">{{ label }}</label>
      <input
        :id="id"
        :type="type"
        :placeholder="placeholder"
        :class="{ 'border-red-500 border': error.length > 0,'focus:shadow-outline':error.length == 0 }"
        class="bg-white focus:outline-none bg-gray-100 rounded py-1 px-4 block w-full appearance-none leading-normal border-red-500"
        :value="value"
        :name="name"
        :autocomplete="autocomplete"
        @input="$emit('input', $event.target.value)"
        @blur="$emit('blur', $event.target.value)"
        @focus="$emit('touch', $event.target.value)"
        @change="$emit('change', $event.target.value)"
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
