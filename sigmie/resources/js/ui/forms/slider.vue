<template>
  <div>
    <label
      v-if="label.length > 0"
      class="block text-sm font-medium leading-5 text-gray-700 pb-1"
      >{{ label }}</label
    >

    <span>
      <span v-if="valueFormat">
        {{ valueFormat(value) }}
      </span>
      <span v-else>
        {{ value }}
      </span>
      <vue-slider
        ref="slider"
        :min="min"
        :max="max"
        :tooltipStyle="{ display: 'none' }"
        :disabled="disabled"
        :processStyle="{ backgroundColor: '#ff927e' }"
        :value="value"
        @change="(value) => $emit('change', value)"
      ></vue-slider>
    </span>
  </div>
</template>

<script>
import VueSlider from "vue-slider-component";
import "vue-slider-component/theme/default.css";

export default {
  props: ["label", "value", "min", "max", "disabled", "valueFormat"],
  components: {
    VueSlider,
  },
  data() {
    return {};
  },
  watch: {},
};
</script>
