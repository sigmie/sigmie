<template>
  <div class="mt-2 flex-row lg:flex lg:space-x-6">
    <!-- This example requires Tailwind CSS v2.0+ -->
    <div>
      <h2 class="text-gray-500 text-xs font-medium uppercase tracking-wide">
        Plans
      </h2>
      <ul
        class="mt-3 grid grid-cols-1 gap-5 sm:gap-6 sm:grid-cols-2 lg:grid-cols-5 w-full"
      >
        <new-plan
          class="col-span-1 flex shadow-sm rounded-md bg-white"
        ></new-plan>
        <plan v-for="(plan, index) in plans" :key="index" class=""></plan>
      </ul>
    </div>
  </div>
</template>

<script>
import Plan from "./_plans/_plan.vue";
import NewPlan from "./_plans/_new-plan.vue";

export default {
  components: {
    Plan,
    NewPlan,
  },
  props: {
    plans: {
      default() {
        return [{}, {}, {}, {}];
      },
    },
  },
  data() {
    return {
      show: false,
    };
  },
};
</script>

<style scoped>
</style>
