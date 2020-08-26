<template>
  <div class="flex">
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
      class="form-checkbox h-4 w-4 text-orange-400 transition duration-150 ease-in-out"
    />
    <label v-if="label" :for="id" class="ml-2 block leading-none text-gray-900">{{ label }}</label>
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
