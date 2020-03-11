<template>
  <div id="bar"></div>
</template>

<script>
import ProgressBar from "progressbar.js";

export default {
  data() {
    return {
      bar: null
    };
  },
  mounted() {
    this.bar = new ProgressBar.Line("#bar", {
      strokeWidth: 2,
      easing: "easeInOut",
      duration: 500,
      color: "#f6ad55",
      trailWidth: 1,
      svgStyle: { width: "100%", height: "100%" }
    });
  },
  methods: {
    animate(...params) {
      this.bar.animate(...params);
    },
    reset() {
      this.bar.set(0);
    }
  }
};
</script>

<style>
#bar {
  position: absolute;
  width: 100%;
  height: 2px;
  top: 0;
}
</style>
