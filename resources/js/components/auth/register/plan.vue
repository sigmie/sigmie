<template>
  <div class="h-full mx-auto container">
    <div class="max-w-sm mx-auto lg:mx-0">
      <card-elevated>
        <div class="w-full row">
          <heading-card text="Subscription plan" />

          <div class="mx-auto pt-6 pb-4">
            <icon width="200px" height="100px" />
          </div>

          <div class="w-full text-center">
            <span class="text-3xl font-base text-gray-800">$ 9</span>
            <span class="text-gray-500 text-base tracking-wide">&nbsp;/ month</span>
          </div>

          <div class="w-full text-center pb-2 border-b mx-6">
            <span class="text-gray-400 text-sm">Price doesn't include your GCP costs.</span>
          </div>

          <ul class="px-8 py-6">
            <li class="py-2 w-full flex text-gray-500">
              <div class="pr-2">
                <icon-check height="18px" width="18px" />
              </div>
              <div class="w-full">
                <span class="text-base">
                  <b class="font-semibold">15 days free trial</b>
                </span>
              </div>
            </li>
            <li class="py-2 w-full flex text-gray-500">
              <div class="pr-2">
                <icon-check height="18px" width="18px" />
              </div>
              <div class="w-full">
                <span class="text-base">
                  <b class="font-semibold">Alerts</b>- we monitor your clusters and even send you mail notification about anything suspicious.
                </span>
              </div>
            </li>
            <li class="py-2 w-full flex text-gray-500">
              <div class="pr-2">
                <icon-check height="18px" width="18px" />
              </div>
              <div class="w-full">
                <span class="text-base">
                  <b class="font-semibold">3 Node cluster</b> for hight availability.
                </span>
              </div>
            </li>
            <li class="py-2 w-full flex text-gray-500">
              <div class="pr-2">
                <icon-check height="18px" width="18px" />
              </div>
              <div class="w-full">
                <span class="text-base">
                  <b class="font-semibold">Metrics</b> we provide metrics about your Elasticseasrch data and indexes.
                </span>
              </div>
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
