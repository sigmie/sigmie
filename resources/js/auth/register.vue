<template>
  <div class="h-full mx-auto container">
    <div class="row m-0 max-w-4xl">
      <div class="col-md-7 col-sm-12">
        <form
          method="POST"
          id="register-form"
          class="mx-auto flex container w-full text-gray-700 h-auto"
          :action="route"
        >
          <csrf-token />

          <div
            class="container flex justify-center w-auto block border-gray-200 border rounded bg-white px-4"
          >
            <div class="row">
              <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10">
                <h1 class="pt-5 pb-2 text-xl">Register</h1>
              </div>
              <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10 border-t mt-3 pt-2">
                <span class="text-xs text-gray-500">Basics</span>
              </div>

              <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10 pt-3">
                <input-field :type="'text'" :name="'email'" :label="'Email'" />
              </div>

              <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10 pt-3">
                <input-field :type="'password'" :name="'password'" :label="'Password'" />
              </div>

              <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10 pt-3">
                <input-field
                  :type="'password'"
                  :name="'password-confirm'"
                  :label="'Confirm password'"
                />
              </div>

              <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 border-t mt-6 pt-2 px-0">
                <span class="text-xs text-gray-500 px-10">Billing</span>

                <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10 pt-3">
                  <input-field
                    :type="'text'"
                    :name="'name'"
                    :label="'Cardholder name'"
                    :required="true"
                    :autocomplete="name"
                  />
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
              </div>

              <input name="method" id="method-field" value type="hidden" />

              <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10 py-4">
                <button
                  id="register-button"
                  class="bg-blue-500 hover:bg-blue-700 text-white text-sm py-2 px-4 rounded uppercase float-right font-semibold tracking-wide"
                >Register</button>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
var stripe = Stripe("pk_test_c9qTG6rra0eQdTd6n7Nhcqka00a3YibJYB");

export default {
  props: ["old", "app", "errors", "route"],
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
