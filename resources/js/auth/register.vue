<template>
  <div class="h-full mx-auto container">
    <div class="mx-auto max-w-sm md:max-w-sm lg:m-0 lg:max-w-md">
      <form
        method="POST"
        id="register-form"
        class="mx-auto flex container w-full text-gray-700 h-auto"
        :action="route"
        v-on:submit.prevent="validate"
      >
        <csrf-token />

        <div
          class="container flex justify-center w-auto block border-gray-200 border rounded bg-white px-4"
        >
          <div class="row">
            <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10">
              <form-heading text="Registers" />
            </div>

            <content-separator text="Basics" />

            <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10 pt-3">
              <input-field
                :type="'text'"
                placeholder="john.doe@gmail.com"
                v-model="email"
                :id="'email-field'"
                :error="mutableErrors.email"
                :name="'email'"
                label="Email"
              />
            </div>

            <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10 pt-3">
              <input-field
                v-model="password"
                :id="'password-field'"
                :type="'password'"
                :name="'password'"
                :error="mutableErrors.password"
                label="Password"
              />
            </div>

            <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10 pt-3 pb-4">
              <input-field
                :id="'password-confirm-field'"
                :type="'password'"
                v-model="passwordConfirm"
                :error="mutableErrors.passwordConfirm"
                :name="'password-confirm'"
                label="Confirm password"
              />
            </div>

            <content-separator text="Billing" />

            <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10 pt-3">
              <input-field
                :id="'name-field'"
                v-model="name"
                placeholder="John Doe"
                :type="'text'"
                :name="'name'"
                :error="mutableErrors.name"
                label="Cardholder name"
                :required="true"
                :autocomplete="'name'"
              />
            </div>

            <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10 pt-3">
              <stripe :intent="app.intent" />
            </div>

            <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10 pt-6 pb-4">
              <primary-button :disabled="disabled" type="submit" text="Register" />
            </div>
          </div>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
export default {
  props: ["old", "app", "errors", "route"],
  components: {
    plan: require("./register/plan").default
  },
  data() {
    return {
      mutableErrors: { ...this.errors },
      disabled: false,
      email: "",
      password: "",
      passwordConfirm: "",
      name: ""
    };
  },
  methods: {
    validate(event) {
      this.mutableErrors = [];

    //   if (this.name.length < 1) {
    //     this.$set(this.mutableErrors, "name", "Name required.");
    //   }

    //   var re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;

    //   if (re.test(String(this.email).toLowerCase())) {
    //     this.$set(this.mutableErrors, "email", "Email required");
    //   }
    }
  }
};
</script>
