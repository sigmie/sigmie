<template>
  <div class="box">
    <div class="mx-auto">
      <label :for="name" class="pb-1 block">{{ label }}</label>
      <input
        :id="id"
        :type="type"
        class="bg-white focus:outline-none focus:shadow-outline bg-gray-200 rounded py-1 px-4 block w-full appearance-none leading-normal is-invalid @enderror"
        :name="name"
        :value="old"
        :required="required"
        :autocomplete="autocomplete"
      />
      <span class="text-red-600 font-semibold" role="alert">
        Wrong input!
      </span>
    </div>
  </div>
</template>

<script>
export default {
  props: {
    name: {
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

  mounted() {
    console.log("plan mount");
  },

  methods: {}
};
</script>
