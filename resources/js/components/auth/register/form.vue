<template>
  <div class="h-full mx-auto container">
    <div class="mx-auto max-w-sm md:max-w-sm lg:m-0 lg:max-w-sm">
      <card-white>
        <form
          method="POST"
          id="register-form"
          class="mx-auto flex container w-full text-gray-700 h-auto"
          :action="route"
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
                v-on:blur="blur"
                v-model.trim="email.value"
                :id="'email-field'"
                :error="mutableErrors.email"
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
                v-on:change="change"
                :error="mutableErrors.password"
                label="Password"
              />
            </div>

            <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10 pt-3 pb-4">
              <form-input
                :id="'password-confirm-field'"
                :type="'password'"
                v-model.trim="passwordConfirm.value"
                :error="mutableErrors.passwordConfirm"
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
                :error="mutableErrors.name"
                label="Cardholder name"
                :required="true"
                :autocomplete="'name'"
              />
            </div>

            <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10 pt-3">
              <stripe :intent="intent" />
            </div>

            <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10 pt-6 pb-4">
              <button-primary :disabled="disabled" type="submit" text="Register" />
            </div>
          </div>
        </form>
      </card-white>
    </div>
  </div>
</template>

<script>
export default {
  props: ["old", "intent", "errors", "route"],
  data() {
    return {
      mutableErrors: { ...this.errors },
      disabled: false,
      email: {
        value: "",
        dirty: "",
        valid: false,
        touched: ""
      },
      password: {
        value: "",
        dirty: "",
        valid: false,
        touched: ""
      },
      passwordConfirm: {
        value: "",
        dirty: "",
        valid: false,
        touched: ""
      },
      name: {
        value: "",
        dirty: "",
        valid: false,
        touched: ""
      },
      disabled: false,
      submited: false
    };
  },
  methods: {
    blur(value) {
      if (this.submited) {
        this.validate();
      }
    },
    change() {
      if (this.submited) {
        this.validate();
      }
    },
    validate() {
      this.mutableErrors = {};
      this.submited = true;

      let passwordRegex = /^(((?=.*[a-z])(?=.*[A-Z]))|((?=.*[a-z]))|((?=.*[A-Z])))(?=.{6,})/;
      let mailRegex = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;

      if (mailRegex.test(this.email.value) === false) {
        this.$set(
          this.mutableErrors,
          "email",
          "The email address that you provided isn't valid."
        );
      }

      if (this.password.value !== this.passwordConfirm.value) {
        this.$set(
          this.mutableErrors,
          "passwordConfirm",
          "The provided passwords don't match."
        );
      }

      if (passwordRegex.test(this.password.value) === false) {
        this.$set(this.mutableErrors, "password", "Password is invalid");
      }

      this.disabled = this.mutableErrors.length > 0;
    }
  }
};
</script>
