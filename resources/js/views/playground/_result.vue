<template>
  <div
    :class="hidden ? 'hidden' : 'block'"
    class="fixed inset-0 overflow-hidden"
  >
    <div class="absolute inset-0 overflow-hidden">
      <section
        class="absolute inset-y-0 pl-16 max-w-full right-0 flex"
        aria-labelledby="slide-over-heading"
      >
        <div
          v-on-clickaway="show ? hideForm : () => null"
          :class="
            show
              ? 'transform transition ease-in-out duration-500 sm:duration-700 translate-x-0'
              : 'transform transition ease-in-out duration-500 sm:duration-700 translate-x-full'
          "
          class="w-screen max-w-md"
        ></div>
      </section>
    </div>
  </div>
</template>

<script>
import delay from "lodash/delay";

export default {
  props: ["result"],
  watch: {},
  data() {
    return {
      hidden: true,
      show: true,
    };
  },
  methods: {
    hide() {
      this.show = false;

      delay(() => {
        this.hidden = true;

        this.$emit("hide");
      }, 700);
    },
  },
};
</script>

<style scoped>
</style>
