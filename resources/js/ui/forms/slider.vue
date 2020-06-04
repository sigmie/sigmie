<template>
  <div>
    <label
      v-if="label.length > 0"
      class="block text-sm font-medium leading-5 text-gray-700 pb-1"
    >{{ label }}</label>

    <span>
      {{ model }}
      <vue-slider
        :min="min"
        :max="max"
        :tooltipStyle="{display:'none'}"
        :disabled="disabled"
        :processStyle="{ backgroundColor:'#ed8936'}"
        v-model="model"
      ></vue-slider>
    </span>
  </div>
</template>

<script>
import VueSlider from "vue-slider-component";
import "vue-slider-component/theme/default.css";

export default {
  props: ["label", "value", "min", "max", "disabled"],
  components: {
    VueSlider
  },
  data() {
    return {
      model: null
    };
  },
  mounted() {
    this.model = this.value;
  },
  watch: {
    model(newValue, oldValue) {
      this.$emit("change", newValue);
    }
  }
};
</script>
