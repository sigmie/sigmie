<template>
  <app title="Payment">
    <div class="flex content-center justify-center h-128">
      <div class="self-center prose">
        <h1 class>Your subscription has expired</h1>
        <p>
          To keep using our application, you need to renew your subscription. Until then your Cluster
          will remain in place but won't get any further updates and fixes.
        </p>

        <div class="float-left w-full sm:w-56 pt-1">
          <inertia-link :href="$route('subscription.create')">
            <button-primary text="Renew your subscription"></button-primary>
          </inertia-link>
        </div>
      </div>
    </div>
  </app>
</template>
<script>
import App from "../layouts/app";

export default {
  components: {
    App,
  },
};
</script>

<style scoped>
</style>