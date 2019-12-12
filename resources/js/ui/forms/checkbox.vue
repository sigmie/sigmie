<template>
  <label class="block text-gray-500">
    <input
      type="checkbox"
      :id="id"
      :required="required"
      :placeholder="placeholder"
      class="mr-2 leading-tight"
      :value="value"
      :name="name"
      :autocomplete="autocomplete"
      @input="$emit('input', $event.target.value)"
      @blur="$emit('blur', $event.target.value)"
      @focus="$emit('touch', $event.target.value)"
      @change="$emit('change', $event.target.value)"
    />
    <slot />
    <span :for="id" class="text-xs">{{ label }}</span>
  </label>
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
  }
};
</script>
