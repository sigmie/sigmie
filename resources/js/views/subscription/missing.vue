<template>
  <app title="Payment" :sidebarDisabled="true">
    <div class="flex content-center justify-center h-128">
      <div class="self-center prose">

        <h1 class>Your subscription is missing</h1>

        <p>
          You account has been created,
          but in order to start using our application,
          you will need an active subscription.
          Afterwards you can cancel your subscritpion at anytime.
        </p>

        <div class="float-left w-full sm:w-40 pt-1">
          <inertia-link :href="$route('subscription.create')">
            <button-primary text="Start your trial"></button-primary>
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