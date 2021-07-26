<template>
  <div
    class="border-gray-200 w-full bg-white shadow overflow-hidden rounded-lg"
  >
    <div class="flex justify-between px-6 py-5">
      <div class>
        <div class="font-semibold text-base text-gray-800">
          Renew your subscription
        </div>
        <div class="text-sm text-gray-600">
          Your subscription ends on
          <time :datetime="ends_at">{{ onlyDate(ends) }}</time>
        </div>
      </div>
      <div class="max-w-sm py-1">
        <inertia-link :href="$route('subscription.create')">
          <button-primary text="Renew"></button-primary>
        </inertia-link>
      </div>
    </div>
  </div>
</template>

<script>
import moment from "moment";
export default {
    props:['ends'],
    methods:{
    onlyDate(datetime) {
      return moment.utc(datetime).format("LL");
    },
    }
};
</script>

<style scoped>
</style>
