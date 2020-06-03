<template>
  <form @submit.prevent="submit">
    <div class="p-6">
      <div class="border-gray-200">
        <div v-if="github">
          <input type="hidden" :value="githubUser.name" name="username" />
          <input type="hidden" :value="githubUser.email" name="email" />
          <input type="hidden" :value="null" name="pasword" />

          <div class="bg-whitesm:px-6">
            <div class="-ml-4 -mt-4 flex justify-between items-center flex-wrap sm:flex-no-wrap">
              <div class="ml-4 mt-4">
                <div class="flex items-center">
                  <div class="flex-shrink-0">
                    <img class="h-12 w-12 rounded-full" :src="githubUser.avatar_url" alt />
                  </div>
                  <div class="ml-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">{{ githubUser.name }}</h3>
                    <p class="text-sm leading-5 text-gray-500">
                      <span href="#">{{ githubUser.email }}</span>
                    </p>
                  </div>
                </div>
              </div>
              <div class="ml-4 mt-4 flex-shrink-0 flex">
                <span class="ml-3 inline-flex rounded-md shadow-sm">
                  <svg class="h-10" fill="currentColor" viewBox="0 0 20 20">
                    <path
                      fill-rule="evenodd"
                      d="M10 0C4.477 0 0 4.484 0 10.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0110 4.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.203 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.942.359.31.678.921.678 1.856 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0020 10.017C20 4.484 15.522 0 10 0z"
                      clip-rule="evenodd"
                    />
                  </svg>
                </span>
              </div>
            </div>
          </div>
        </div>
        <div v-else>
          <div>
            <h3 class="text-lg leading-6 font-medium text-gray-900">Sign up With Github</h3>
          </div>

          <button-github :route="githubRoute" class="mt-3" />

          <div class="mt-4 relative">
            <div class="absolute inset-0 flex items-center">
              <div class="w-full border-t border-gray-300"></div>
            </div>
            <div class="relative flex justify-center text-sm leading-5">
              <span class="px-2 bg-white text-gray-500">Or</span>
            </div>
          </div>

          <div class="sm:col-span-3 pb-2">
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

          <div class="sm:col-span-3 pb-2">
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
          <div class="sm:col-span-3 pb-2">
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
          <div class="sm:col-span-3 pb-2">
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
        </div>
      </div>
    </div>
    <div class="border-t border-gray-200 p-6">
      <form-select
        label="Plan"
        name="plan"
        id="plan"
        v-model.trim="$v.plan.$model"
        aria-label="Billing plan"
        :items="paddlePlans"
        :validations="$v.plan"
      ></form-select>

      <paddle class="mt-4" :plan="plan"></paddle>
    </div>

    <div class="border-gray-200 px-5 pb-5 pt-1">
      <div class="flex justify-end">
        <span class="ml-3 inline-flex rounded-md shadow-sm">
          <button-primary :class="{ 'disabled': $v.$anyError }" text="Register" type="submit"></button-primary>
        </span>
      </div> </div>
  </form>
</template>

<script>
import {
  required,
  minLength,
  between,
  email,
  sameAs,
  helpers
} from "vuelidate/lib/validators";

import forEach from "lodash/forEach";
import findKey from "lodash/findKey";

const mustBeTrue = value => value === true;

export default {
  props: ["githubRoute", "githubUser", "paddlePlans"],
  data() {
    return {
      name: this.$page.old.name ? this.$page.old.name : "",
      email: this.$page.old.email ? this.$page.old.email : "",
      password: "",
      password_confirmation: "",
      username: this.$page.old.username ? this.$page.old.username : "",
      plan: this.$page.old.plan ? this.$page.old.plan : "",
      method: "",
      consent: false,
      github: false,
      errorMessages: {
        email: {
          required: "Email address is required.",
          email: "Invalid email address format."
        },
        password: {
          minLength: "Password should contain at least 8 chars.",
          required: "Password can't be empty."
        },
        username: {
          required: "Username is required.",
          minLength: "Username should contain at least 4 chars."
        },
        password_confirmation: {
          sameAsPassword: "Passwords don't match."
        },
        name: {
          required: "Name field is required."
        },
        consent: {}
      }
    };
  },
  validations: {
    password: {
      required,
      minLength: minLength(8)
    },
    password_confirmation: {
      sameAsPassword: sameAs("password")
    },
    name: {
      required
    },
    consent: {
      mustBeTrue
    },
    username: {
      required,
      minLength: minLength(4)
    },
    plan: {
      required
    },
    email: {
      required,
      email
    },
    method: {
      required
    }
  },
  methods: {
    async submit(event) {
      if (this.$v.$anyError === false) {
        event.target.submit();
      }
    },
    set(key, value) {
      this[key] = value;
      this.$v[key].$touch();
    }
  },
  beforeMount() {
    this.github = typeof this.githubUser.name !== "undefined";
  },
};
</script>

<style>
</style>
