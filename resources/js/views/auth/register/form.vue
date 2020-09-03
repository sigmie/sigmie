<template>
  <form @submit.prevent>
    <github :github-user="githubUser"></github>

    <div v-if="!githubUser" class="mt-4 relative">
      <div class="absolute inset-0 flex items-center">
        <div class="w-full border-t border-gray-300"></div>
      </div>
      <div class="relative flex justify-center text-sm leading-5">
        <span class="px-2 bg-gray-50 text-gray-500">Or</span>
      </div>
    </div>

    <div class="pb-5">
      <div class="border-gray-200">
        <div class="sm:col-span-3 pb-5" v-if="!githubUser">
          <form-input
            :value="username"
            @change="(value) => set('username',value)"
            class="pt-4"
            id="username"
            name="username"
            type="text"
            label="Username"
            :validations="$v.username"
            :error-messages="errorMessages.username"
          ></form-input>
        </div>

        <div class="sm:col-span-3 pb-5" v-if="!githubUser">
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
        </div>
        <div class="sm:col-span-3 pb-5" v-if="!githubUser">
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
        </div>
        <div class="sm:col-span-3 pb-5" v-if="!githubUser">
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
        </div>
        <div class="sm:col-span-3 pb-5">
          <form-checkbox
            class="pt-4"
            name="consent"
            id="consent"
            :value="consent"
            @change="(value) => set('consent',value)"
            :required="true"
          >
            <label class="ml-2 block leading-6 text-sm text-gray-500" for="terms">
              I agree to the
              <inertia-link
                target="_blank"
                class="border-b"
                :href="$route('legal.terms')"
              >Terms of Service</inertia-link>&nbsp;and
              <inertia-link
                target="_blank"
                class="border-b"
                :href="$route('legal.privacy')"
              >Privacy Policy</inertia-link>.
            </label>
          </form-checkbox>
        </div>
      </div>
    </div>

    <button-primary
      :class="{ 'disabled': $v.$anyError }"
      text="Create account"
      @click="createUser"
      type="submit"
    ></button-primary>
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

import forEach from "lodash/forEach";
import findKey from "lodash/findKey";

const mustBeTrue = (value) => value === true;

export default {
  components: {
    github: require("./github").default,
  },
  props: ["githubUser"],
  data() {
    return {
      name: this.$page.old.name ? this.$page.old.name : "",
      email: this.$page.old.email ? this.$page.old.email : "",
      password: "",
      password_confirmation: "",
      username: this.$page.old.username ? this.$page.old.username : "",
      consent: false,
      github: false,
      avatar_url: null,
      paylink: "",
      errorMessages: {
        email: {
          required: "Email address is required.",
          email: "Invalid email address format.",
          isUnique: "This email already exists.",
        },
        password: {
          minLength: "Password should contain at least 8 chars.",
          required: "Password can't be empty.",
        },
        username: {
          required: "Username is required.",
          minLength: "Username should contain at least 4 chars.",
        },
        password_confirmation: {
          sameAsPassword: "Passwords don't match.",
        },
        name: {
          required: "Name field is required.",
        },
        consent: {},
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
    consent: {
      mustBeTrue,
    },
    username: {
      required,
      minLength: minLength(4),
    },
    email: {
      required,
      email,
      isUnique(value) {
        if (value === "") {
          return true;
        }

        let route = this.$route("user.validate.email", { email: value });

        return this.$http.get(route).then((response) => response.data.valid);
      },
    },
  },
  methods: {
    set(key, value) {
      this[key] = value;
      this.$v[key].$touch();
    },
    async createUser() {
      if (this.$v.$invalid && this.github === false) {
        return;
      }

      let response = await this.$http.post(this.$route("register"), {
        email: this.email,
        username: this.username,
        password: this.password,
        github: this.github,
        avatar_url: this.avatar_url,
      });

      if (response.data.registered) {
        this.$inertia.visit(this.$route("subscription.create"));
      }
    },
  },
  mounted() {
    if (this.githubUser) {
      this.email = this.githubUser.email;
      this.username = this.githubUser.name;
      this.avatar_url = this.githubUser.avatar_url;
      this.github = true;
    }
  },
};
</script>

<style>
</style>
