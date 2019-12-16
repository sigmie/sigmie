<template>
  <div class="h-full mx-auto container">
    <div class="max-w-sm">
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

          <div class="w-full text-center pb-2 border-b mx-6">
            <span class="text-gray-300 text-sm">Price doesn't include your GCP costs.</span>
          </div>

          <ul class="px-8 py-6">
            <li class="py-2 w-full flex text-gray-400">
              <div class="pr-2"
              >
                <icon-check height="18px" width="18px" />
              </div>
              <span class="text-base">
                <b class="font-semibold">Lorem, ipsum dolor</b> sit amet consectetur adipisicing elit.
              </span>
            </li>
          </ul>
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
