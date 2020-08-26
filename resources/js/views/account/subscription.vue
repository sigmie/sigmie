<template>
  <div>
    <modal
      title="Are you sure ?"
      primaryText="Yes"
      secondaryText="Cancel"
      content="Proceeding will cancel your subscription. You won't be able to use the application features but your
      account and your cluster will remain in place."
      @primaryAction="cancelSubscription"
      @secondaryAction="showConfirmation = false"
      @clickAway="showConfirmation = false"
      @onEsc="showConfirmation = false"
      :icon="true"
      :show="showConfirmation"
      type="danger"
    ></modal>

    <div class="bg-white shadow overflow-hidden rounded-lg">
      <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
        <h3 class="text-lg leading-6 font-medium text-gray-900">Subscription</h3>
        <p class="mt-1 max-w-2xl text-sm leading-5 text-gray-500">
          Your subscription is managed with
          <a
            class="text-orange-500"
            target="_blank"
            href="http://paddle.com/"
          >Paddle</a>.
        </p>
      </div>

      <dl
        class="grid grid-cols-1 col-gap-4 row-gap-8 sm:grid-cols-2 px-3 sm:px-6 py-4"
        v-if="data.was_subscribed"
      >
        <div class="sm:col-span-1">
          <dt class="text-sm leading-5 font-medium text-gray-500">Billing email</dt>
          <dd class="mt-1 text-sm leading-5 text-gray-900">{{ data.email }}</dd>
        </div>
        <div class="sm:col-span-1">
          <dt class="text-sm leading-5 font-medium text-gray-500">Plan</dt>
          <dd class="mt-1 text-sm leading-5 text-gray-900">{{ data.plan }}</dd>
        </div>

        <div class="sm:col-span-1" v-if="data.last_payment && data.on_trial === false">
          <dt class="text-sm leading-5 font-medium text-gray-500">Last payment on</dt>
          <dd class="mt-1 text-sm leading-5 text-gray-900">
            <time :datetime="data.last_payment">{{ onlyDate(data.last_payment) }}</time>
          </dd>
        </div>

        <div class="sm:col-span-1" v-if="data.on_trial">
          <dt class="text-sm leading-5 font-medium text-gray-500">Trial until</dt>
          <dd class="mt-1 text-sm leading-5 text-gray-900">
            <time :datetime="data.trial_ends_at">{{ onlyDate(data.trail_ends_at) }}</time>
          </dd>
        </div>
        <div class="sm:col-span-1" v-if="data.canceled === false">
          <dt class="text-sm leading-5 font-medium text-gray-500">Next payment on</dt>
          <dd class="mt-1 text-sm leading-5 text-gray-900">
            <time :datetime="data.next_payment">{{ onlyDate(data.next_payment) }}</time>
          </dd>
        </div>
      </dl>

      <div class="bg-gray-50 px-3 sm:px-6 py-4" v-if="data.payment_method === 'paypal'">
        <dl class="grid grid-cols-1 col-gap-4 row-gap-8 sm:grid-cols-3">
          <div class="sm:col-span-1">
            <dt class="text-sm leading-5 font-medium text-gray-500">Payment method</dt>
            <dd class="mt-1 text-sm leading-5 text-gray-900">PayPal</dd>
          </div>
        </dl>
      </div>

      <div class="bg-gray-50 px-3 sm:px-6 py-4" v-if="data.payment_method === 'card'">
        <dt class="text-sm leading-5 mb-3 font-medium text-gray-500">Card details</dt>
        <dl class="grid grid-cols-1 col-gap-4 row-gap-8 sm:grid-cols-3">
          <div class="sm:col-span-1">
            <dt class="text-sm leading-5 font-medium text-gray-500">Type</dt>
            <dd class="mt-1 text-sm leading-5 text-gray-900">{{ startCase(data.card_brand) }}</dd>
          </div>
          <div class="sm:col-span-1">
            <dt class="text-sm leading-5 font-medium text-gray-500">Card number</dt>
            <dd class="mt-1 text-sm leading-5 text-gray-900">************{{ data.card_last_four }}</dd>
          </div>
          <div class="sm:col-span-1">
            <dt class="text-sm leading-5 font-medium text-gray-500">Expire date</dt>
            <dd class="mt-1 text-sm leading-5 text-gray-900">{{ data.card_expire_date }}</dd>
          </div>
        </dl>
      </div>

      <div class="sm:col-span-2 py-5 px-6" v-if="data.was_subscribed">
        <dt class="text-sm leading-5 hidden sm:block mb-3 font-medium text-gray-500">Manage</dt>
        <dd class="mt-1 text-sm leading-5 text-gray-900" v-if="data.canceled">
          <div class="flex flex-col">
            <div class="w-full md:w-60 mb-2">
              <inertia-link :href="$route('subscription.create')">
                <button-primary text="Renew your subscription"></button-primary>
              </inertia-link>
            </div>
            <div class="text-sm leading-5 text-gray-500">
              Your subscription ends on
              <time :datetime="data.ends_at">{{ onlyDate(data.ends_at) }}</time>
            </div>
          </div>
        </dd>
        <dd class="mt-1 text-sm leading-5 text-gray-900" v-else>
          <div class="w-60">
            <button-danger @click="showConfirmation = true" text="Cancel subscription"></button-danger>
          </div>
        </dd>
      </div>
      <div class="sm:col-span-2 py-5 px-6" v-if="data.was_subscribed === false">
        <div class="flex flex-col">
          <div class="text-sm leading-5 mb-2 text-gray-500">
            You haven't subscribed yet, click
            <inertia-link class="text-orange-500" :href="$route('subscription.create')">here</inertia-link> to start your trial.
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import moment from "moment";
import startCase from "lodash/startCase";

export default {
  props: ["data"],
  data() {
    return {
      showConfirmation: false,
    };
  },
  methods: {
    onlyDate(datetime) {
      return moment.utc(datetime).format("LL");
    },
    startCase(string) {
      return startCase(string);
    },
    cancelSubscription() {
      this.$inertia.visit(this.$route("subscription.cancel"), {
        method: "post",
        data: {},
        replace: false,
        preserveState: false,
        preserveScroll: false,
        only: [],
      });
    },
  },
};
</script>

<style scoped>
</style>