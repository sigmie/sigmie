<template>
  <div class="flex items-center">

    <input
      :id="id"
      type="checkbox"
      :required="required"
      :value="value"
      :name="name"
      :checked="checked"
      @change="$emit('change', $event.target.checked)"
      @blur="$emit('blur', $event.target.value)"
      @focus="$emit('touch', $event.target.value)"
      class="h-4 w-4 text-theme-orange-light-800 border-gray-300 rounded"
    />
    <label v-if="label" :for="id" class="ml-2 block text-sm text-gray-900">{{ label }}</label>
    <slot />
  </div>
</template>

<script>
export default {
  model: {
    prop: "checked",
    event: "change",
  },
  props: {
    name: {
      default: "",
    },
    valid: {
      default: null,
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
    id: {
      default: "",
    },
    checked: {
      default: false,
    },
    required: {
      default: false,
    },
  },
};
</script>
