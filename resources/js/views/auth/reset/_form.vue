<template>
  <form
    @submit.prevent
    class="flex flex-col w-full px-4"
    method="POST"
    :action="$route('password.update')"
  >
    <h2 class="text-base mb-5 font-semibold text-gray-800">Password reset</h2>
    <form-input
      :value="email"
      @change="(value) => set('email',value)"
      class="pt-4"
      id="email"
      name="email"
      type="text"
      label="Email address"
      :validations="$v.email"
      :error-messages="errorMessages.email"
    ></form-input>

    <form-input
      :value="password"
      @change="(value) => set('password',value)"
      class="pt-4"
      id="password"
      name="password"
      type="password"
      label="Password"
      :validations="$v.password"
      :error-messages="errorMessages.password"
    ></form-input>
    <form-input
      :value="password_confirmation"
      @change="(value) => set('password_confirmation',value)"
      class="pt-4"
      id="password_cofirmation"
      name="password_confirmation"
      type="password"
      label="Password confirm"
      :validations="$v.password_confirmation"
      :error-messages="errorMessages.password_confirmation"
    ></form-input>
    <div class="pt-4">
      <button-primary @click="submit" text="Change password" type="submit"></button-primary>
    </div>
  </form>
</template>

<script>
import {
  required,
  minLength,
  between,
  email,
  sameAs,
  helpers,
} from "vuelidate/lib/validators";

export default {
  props: ["token"],
  data() {
    return {
      email: this.$page.props.old.email ? this.$page.props.old.email : "",
      password: "",
      password_confirmation: "",
      errorMessages: {
        email: {
          required: "Email address is required.",
          email: "Invalid email address format.",
        },
        password: {
          minLength: "Password should contain at least 8 chars.",
          required: "Password can't be empty.",
        },
        password_confirmation: {
          sameAsPassword: "Passwords don't match.",
        },
      },
    };
  },
  validations: {
    password: {
      required,
      minLength: minLength(8),
    },
    password_confirmation: {
      sameAsPassword: sameAs("password"),
    },
    email: {
      required,
      email,
    },
  },
  methods: {
    submit() {
      let email = this.email;
      let password = this.password;
      let password_confirmation = this.password_confirmation;
      let token = this.token;

      this.$inertia.post(this.$route("password.update"), {
        email,
        token,
        password,
        password_confirmation,
      });
    },
    set(key, value) {
      this[key] = value;
      this.$v[key].$touch();
    },
  },
};
</script>

<style>
</style>
