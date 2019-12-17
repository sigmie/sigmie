<template>
  <label class="block text-gray-500">
    <input
      type="checkbox"
      :id="id"
      :required="required"
      class="mr-2 leading-tight"
      :value="value"
      :name="name"
      :checked="checked"
      @change="$emit('change', $event.target.checked)"
      @blur="$emit('blur', $event.target.value)"
      @focus="$emit('touch', $event.target.value)"
    />
    <slot />
    <span :for="id" class="text-xs">{{ label }}</span>
  </label>
</template>

<script>
export default {
  model: {
    prop: 'checked',
    event: 'change'
  },
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
    id: {
      default: ""
    },
    checked: {
      default: false
    },
    required: {
      default: true
    }
  }
};
</script>
