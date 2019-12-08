<template>
  <div class="h-full mx-auto container">
    <div class="row m-0">
      <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10 pt-3">
        <div class="box">
          <div class="mx-auto">
            <csrf-token></csrf-token>
            <input
              type="text"
              class="bg-white focus:outline-none focus:shadow-outline bg-gray-200 rounded py-1 px-4 block w-full appearance-none leading-normal @error('name') is-invalid @enderror"
              name="method"
              v-model="stripe.method"
            />
            <label for="name" class="pb-1 block">Cardholder name</label>
            <input
              id="card-holder-name"
              type="text"
              class="bg-white focus:outline-none focus:shadow-outline bg-gray-200 rounded py-1 px-4 block w-full appearance-none leading-normal @error('name') is-invalid @enderror"
              name="name"
              v-model="stripe.holder"
              required
              autocomplete="name"
              autofocus
            />
            <!-- <span class="invalid-feedback" role="alert">
              <strong>{{ $message }}</strong>
            </span>-->
            c
            <input
              type="text"
              class="bg-white focus:outline-none focus:shadow-outline bg-gray-200 rounded py-1 px-4 block w-full appearance-none leading-normal @error('name') is-invalid @enderror"
              name="method"
              v-model="stripe.method"
            />
            <label for="name" class="pb-1 block">Cardholder name</label>
            <input
              id="card-holder-name"
              type="text"
              class="bg-white focus:outline-none focus:shadow-outline bg-gray-200 rounded py-1 px-4 block w-full appearance-none leading-normal @error('name') is-invalid @enderror"
              name="name"
              v-model="stripe.holder"
              required
              autocomplete="name"
              autofocus
            />
            <!-- <span class="invalid-feedback" role="alert">
              <strong>{{ $message }}</strong>
            </span>-->
          </div>
        </div>
      </div>
      <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10 pt-3">
        <div class="box">
          <div class="mx-auto">
            <label for="name" class="pb-1 block">Credit card</label>
            <div
              id="card-element"
              class="bg-white focus:outline-none focus:shadow-outline bg-gray-200 rounded py-1 px-4 block w-full appearance-none leading-normal @error('name') is-invalid @enderror"
            ></div>
            <div id="card-errors" role="alert"></div>
          </div>
        </div>
      </div>

      <input name="method" id="method-field" value type="hidden" />

      <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10 py-4">
        <button
          @click="handleSubmit"
          id="register-button"
          class="bg-blue-500 hover:bg-blue-700 text-white text-sm py-2 px-4 rounded uppercase float-right font-semibold tracking-wide"
        >Register</button>
      </div>
    </div>
    <div class="col-md-5 col-sm-12 first-xs last-md">
      <div class="row m-0">
        <plan />
      </div>
    </div>
  </div>
</template>

<script>
var stripe = Stripe("pk_test_c9qTG6rra0eQdTd6n7Nhcqka00a3YibJYB");

export default {
  props: ["old", "app"],
  components: {
    plan: require("./register/plan").default
  },

  data() {
    return {
      stripe: {
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
      }
    };
  },

  mounted() {
    let elements = stripe.elements();

    this.card = elements.create("card", {
      style: this.stripe.style,
      hidePostalCode: true
    });

    this.card.mount("#card-element");
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
            billing_details: { name: this.stripe.holder }
          }
        }
      );

      if (error) {
        console.log(error);
      } else {
        this.stripe.method = setupIntent.payment_method;
        // form.submit();
      }
    }
  }
};
</script>
