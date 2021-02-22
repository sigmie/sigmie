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
        <h3 class="text-lg leading-6 font-medium text-gray-900">
          Subscription
        </h3>
        <p class="mt-1 max-w-2xl text-sm leading-5 text-gray-500">
          Your subscription is managed with
          <a
            class="text-theme-orange-light-900"
            target="_blank"
            href="http://paddle.com/"
            >Paddle</a
          >.
        </p>
      </div>

      <div
        class="bg-green-50 px-3 sm:px-6 py-4"
        v-if="$page.props.flash.success"
      >
        <div class="text-sm leading-5 text-green-700">
          <ul>
            <li>{{ $page.props.flash.success }}</li>
          </ul>
        </div>
      </div>

      <dl
        class="grid grid-cols-1 gap-x-4 gap-y-8 sm:grid-cols-2 px-3 sm:px-6 py-4"
        v-if="data.was_subscribed"
      >
        <div class="sm:col-span-1">
          <dt class="text-sm leading-5 font-medium text-gray-500">
            Billing email
          </dt>
          <dd class="mt-1 text-sm leading-5 text-gray-900">{{ data.email }}</dd>
        </div>
        <div class="sm:col-span-1">
          <dt class="text-sm leading-5 font-medium text-gray-500">Plan</dt>
          <dd class="mt-1 text-sm leading-5 text-gray-900">{{ data.plan }}</dd>
        </div>

        <div
          class="sm:col-span-1"
          v-if="data.last_payment && data.on_trial === false"
        >
          <dt class="text-sm leading-5 font-medium text-gray-500">
            Last payment on
          </dt>
          <dd class="mt-1 text-sm leading-5 text-gray-900">
            <time :datetime="data.last_payment">{{
              onlyDate(data.last_payment)
            }}</time>
          </dd>
        </div>

        <div class="sm:col-span-1" v-if="data.on_trial">
          <dt class="text-sm leading-5 font-medium text-gray-500">
            Trial until
          </dt>
          <dd class="mt-1 text-sm leading-5 text-gray-900">
            <time :datetime="data.trial_ends_at">{{
              onlyDate(data.trail_ends_at)
            }}</time>
          </dd>
        </div>
        <div class="sm:col-span-1" v-if="data.canceled === false">
          <dt class="text-sm leading-5 font-medium text-gray-500">
            Next payment on
          </dt>
          <dd class="mt-1 text-sm leading-5 text-gray-900">
            <time :datetime="data.next_payment">{{
              onlyDate(data.next_payment)
            }}</time>
          </dd>
        </div>
      </dl>

      <div class="sm:col-span-2 py-5 px-6" v-if="data.was_subscribed === false">
        <div class="flex flex-col">
          <div class="text-sm leading-5 mb-2 text-gray-500">
            You haven't subscribed yet, click
            <inertia-link
              class="text-theme-orange-light-900"
              :href="$route('subscription.create')"
            >
              here </inertia-link
            >to start your trial.
          </div>
        </div>
      </div>
    </div>

    <div v-if="data.canceled" class="relative py-5">
      <div class="absolute inset-0 flex items-center" aria-hidden="true">
        <div class="w-full border-t border-gray-200"></div>
      </div>
      <div class="relative flex justify-center"></div>
    </div>

    <renew-subscription v-if="data.canceled" :ends="data.ends_at">
    </renew-subscription>

    <div
      v-if="data.was_subscribed && data.canceled === false"
      class="relative py-5"
    >
      <div class="absolute inset-0 flex items-center" aria-hidden="true">
        <div class="w-full border-t border-gray-200"></div>
      </div>
      <div class="relative flex justify-center"></div>
    </div>

    <payment-update
      :currentType="data.payment_method"
      :expireDate="data.card_expire_date"
      :lastFour="data.card_last_four"
      :url="data.method_update_url"
      :vendor="data.vendor"
      v-if="data.was_subscribed && data.canceled === false"
    >
    </payment-update>

    <div
      v-if="data.was_subscribed && data.canceled === false"
      class="relative py-5"
    >
      <div class="absolute inset-0 flex items-center" aria-hidden="true">
        <div class="w-full border-t border-gray-200"></div>
      </div>
      <div class="relative flex justify-center"></div>
    </div>

    <div
      v-if="data.was_subscribed && data.canceled === false"
      class="border-gray-200 w-full bg-white shadow overflow-hidden rounded-lg"
    >
      <div class="flex justify-between px-6 py-5">
        <div class>
          <div class="font-semibold text-base text-gray-800">
            Cancel Subscription
          </div>
          <div class="text-sm text-gray-600">
            Your subscription will remain active until the end of your trail.
          </div>
        </div>
        <div class="max-w-sm py-1">
          <button-danger
            @click="showConfirmation = true"
            text="Cancel"
          ></button-danger>
        </div>
      </div>
    </div>

    <div v-if="data.receipts.length > 0" class="relative py-5">
      <div class="absolute inset-0 flex items-center" aria-hidden="true">
        <div class="w-full border-t border-gray-200"></div>
      </div>
      <div class="relative flex justify-center"></div>
    </div>

    <receipts v-if="data.receipts.length > 0" :receipts="data.receipts">
    </receipts>
  </div>
</template>

<script>
import moment from "moment";
import startCase from "lodash/startCase";
import receipts from "./_receipts";
import paymentUpdate from "./_payment-update";
import renewSubscription from "./_renew-subscription";

export default {
  props: ["data"],
  components: {
    receipts,
    paymentUpdate,
    renewSubscription,
  },
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
