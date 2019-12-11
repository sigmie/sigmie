<template>
  <div class="box">
    <label for="name" class="pb-1 block text-gray-600 font-normal text-sm">Credit card</label>
    <div
      id="card-element"
      class="bg-white focus:outline-none focus:shadow-outline bg-gray-200 rounded py-1 px-4 block w-full appearance-none leading-normal @error('name') is-invalid @enderror"
    ></div>
    <div id="card-errors" role="alert"></div>
    <input name="method" id="method-field" value type="hidden" />
  </div>
</template>

<script>
var stripe = Stripe("pk_test_c9qTG6rra0eQdTd6n7Nhcqka00a3YibJYB");

export default {
  props: {
    text: {
      default: ""
    },
    intent: {
      default: "",
      required: true
    }
  },
  data() {
    return {
      card: null,
      holder: "",
      method: null,
      style: {
        base: {
          backgroundColor: "#edf2f7",
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
    async handleSubmit(e) {
      e.preventDefault();

      let client_secret = this.app.intent.client_secret;

      const { setupIntent, error } = await stripe.handleCardSetup(
        client_secret,
        this.card,
        {
          payment_method_data: {
            billing_details: { name: this.holder }
          }
        }
      );

      if (error) {
        console.log(error);
      } else {
        this.method = setupIntent.payment_method;
        // form.submit();
      }
    }
  },
  mounted() {
    let elements = stripe.elements();

    this.card = elements.create("card", {
      style: this.style,
      hidePostalCode: true
    });

    this.card.mount("#card-element");
  }
};
</script>

