<template>
  <div class="h-full mx-auto container">
    <div class="mx-auto max-w-sm md:max-w-sm lg:m-0 lg:max-w-sm">
      <card-white>
        <form
          method="POST"
          id="register-form"
          class="mx-auto flex container w-full text-gray-700 h-auto"
          :action="action"
          v-on:submit.prevent="validate"
        >
          <csrf />

          <div class="row">
            <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10">
              <heading-form text="Registers" />
            </div>

            <divider-form text="Basics" />

            <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10 pt-3">
              <form-input
                :type="'text'"
                placeholder="john.doe@gmail.com"
                @blur="blur"
                @change="change"
                v-model.trim="email.value"
                :id="'email-field'"
                :error="email.errors[0]"
                :name="'email'"
                label="Email"
              />
            </div>

            <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10 pt-3">
              <form-input
                v-model="password.value"
                :id="'password-field'"
                :type="'password'"
                :name="'password'"
                @blur="blur"
                @change="change"
                :error="password.errors[0]"
                label="Password"
              />
            </div>

            <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10 pt-3 pb-4">
              <form-input
                :id="'password-confirm-field'"
                :type="'password'"
                v-model.trim="passwordConfirm.value"
                @blur="blur"
                @change="change"
                :error="passwordConfirm.errors[0]"
                :name="'password-confirm'"
                label="Confirm password"
              />
            </div>

            <divider-form text="Billing" />

            <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10 pt-3">
              <form-input
                :id="'name-field'"
                v-model.trim="name.value"
                placeholder="John Doe"
                :type="'text'"
                name="name"
                @blur="blur"
                @change="change"
                :error="name.errors[0]"
                label="Cardholder name"
                :required="true"
                :autocomplete="'name'"
              />
            </div>

            <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10 pt-3">
              <stripe :name="name.value" ref="stripe" :intent="intent" />
            </div>

            <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10 pt-3">
              <form-checkbox
                id="policy-field"
                v-model="policy.value"
                :required="true"
                @blur="blur"
                @change="change"
              >
                <span for="policy-field" class="text-xs">
                  I agree to the
                  <a
                    class="underline cursor-pointer"
                    :href="termsRoute"
                    target="_blank"
                  >Terms of Service</a> and
                  <a
                    class="underline cursor-pointer"
                    target="_blank"
                    :href="privacyRoute"
                  >Privacy Policy</a>.
                </span>
              </form-checkbox>
            </div>

            <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10 pt-6 pb-4">
              <button-primary
                @click="onSubmit"
                :disabled="state === 'invalid'"
                type="submit"
                text="Register"
              />
            </div>
          </div>
        </form>
      </card-white>
    </div>
  </div>
</template>

<script>
export default {
  props: ["intent", "old", "action", "errors", "privacyRoute", "termsRoute"],
  data() {
    return {
      email: {
        errors: this.errors.email ? this.errors.email : [],
        value: this.old.email ? this.old.email : ""
      },
      policy: {
        value: false
      },
      password: {
        errors: this.errors.password ? this.errors.password : [],
        value: ""
      },
      passwordConfirm: {
        errors: this.errors.passwordConfirm ? this.errors.passwordConfirm : [],
        value: ""
      },
      name: {
        errors: this.errors.name ? this.errors.name : [],
        value: this.old.name ? this.old.name : ""
      },
      state: "clean"
    };
  },
  methods: {
    blur(value) {
      if (this.state === "invalid") {
        this.validate();
      }
    },
    async onSubmit() {
      this.validate();

      await this.$refs.stripe.fetchMethod();

      if (this.state === "valid") {
        let form = document.getElementById("register-form");
        form.submit();
      }
    },
    change() {
      if (this.state === "invalid") {
        this.validate();
      }
    },
    validate() {
      this.state = "valid";
      this.email.errors = [];
      this.password.errors = [];
      this.passwordConfirm.errors = [];
      this.name.errors = [];

      let mailRegex = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;

      if (this.email.value === "") {
        this.state = "invalid";
        this.email.errors.push("The email adress field is required.");
      }

      if (this.password.value === "") {
        this.state = "invalid";
        this.password.errors.push("The password field is required.");
      }

      if (this.name.value === "") {
        this.state = "invalid";
        this.name.errors.push("The name field is required.");
      }

      if (mailRegex.test(this.email.value) === false) {
        this.state = "invalid";
        this.email.errors.push("The provided email address isn't valid.");
      }

      if (this.policy.value === false) {
        this.state = "invalid";
      }

      if (this.password.value !== this.passwordConfirm.value) {
        this.state = "invalid";
        this.passwordConfirm.errors.push("The provided passwords don't match.");
      }

      if (this.password.length >= 8) {
        this.state = "invalid";
        this.password.errors.push(
          "Password should be eight characters or longer."
        );
      }
    }
  }
};
</script>
