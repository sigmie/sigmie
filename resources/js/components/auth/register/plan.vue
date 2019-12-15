<template>
  <div class="h-full mx-auto container">
    <div class="max-w-sm md:max-w-xs lg:m-0 lg:max-w-sm">
      <card-elevated>
        <div class="w-full row">
          <heading-card text="Subscription plan" />

          <div class="mx-auto pt-6 pb-4">
            <icon width="200px" height="100px" />
          </div>

          <div class="w-full text-center">
            <span class="text-3xl font-base text-gray-800">$ 24</span>
            <span class="text-gray-500 text-base tracking-wide">&nbsp;/ month</span>
          </div>

          <div class="w-full text-center pb-2 border-b mx-6 mb-5">
            <span class="text-gray-400 text-sm">Price doesn't include your GCP costs.</span>
          </div>
        </div>
      </card-elevated>
    </div>
  </div>
</template>

<script>
export default {
  props: ["old"],
  components: {
    icon: require("./icon").default
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
