<template>
  <div @change="fetchMethod">
    <label for="method" class="block text-sm font-medium leading-5 text-gray-700 pb-1">Credit card</label>
    <div
      id="card-element"
      class="appearance-none block w-full px-3 py-3 border border-gray-300 rounded-md placeholder-gray-400 focus:outline-none focus:border-blue-300 transition duration-150 ease-in-out sm:text-sm sm:leading-5"
    ></div>
    <div id="card-errors" role="alert"></div>
    <input
      name="method"
      v-bind:value="value"
      v-on:input="$emit('input', $event.target.value)"
      id="method"
      type="hidden"
    />
  </div>
</template>

<script>
export default {
  props: {
    text: {
      default: ""
    },
    name: {
      default: ""
    },
    value: {}
  },
  methods() {},
  data() {
    return {
      card: null,
      method: null,
      stripe: null,
      style: {
        base: {
          backgroundColor: "#ffffff",
          fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
          fontSmoothing: "antialiased",
          fontSize: "16px",
          "::placeholder": {
            color: "#aab7c4"
          }
        },
        invalid: {
          color: "#fa755a",
          iconColor: "#fa755a"
        }
      }
    };
  },
  methods: {
    async fetchMethod() {
      let client_secret = stripe.intent.client_secret;

      const { setupIntent, error } = await this.stripe.handleCardSetup(
        client_secret,
        this.card,
        {
          payment_method_data: {
            billing_details: { name: this.name }
          }
        }
      );

      if (error) {
        this.$emit("input", "");
      } else {
        this.$emit("input", setupIntent.payment_method);
      }
    }
  },
  mounted() {
    this.stripe = Stripe(stripe.secret);

    let elements = this.stripe.elements();

    this.card = elements.create("card", {
      style: this.style,
      hidePostalCode: true
    });

    this.card.mount("#card-element");
  }
};
</script>

<style>
.StripeElement {
  box-sizing: border-box;
  padding: 0.5rem 0.75rem 0.5rem 0.75rem;
  background-color: #ffffff;
  border-radius: 0.375rem;
  line-height: 1.5;
}

.StripeElement--focus {
}

.StripeElement--invalid {
  background-color: "red";
  border-color: #f8b4b4;
}

.StripeElement--webkit-autofill {
  background-color: "red";
  background-color: #ffffff !important;
}
</style>
