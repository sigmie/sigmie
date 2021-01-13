<template>
  <layout title="Sign Up">
    <div class="min-h-screen flex flex-col justify-center sm:px-6 lg:px-8" v-cloak>
      <div class="sm:mx-auto sm:w-full sm:max-w-md">
        <a :href="$route('landing')" class="mx-auto">
          <logo-default class="mx-3 sm:mx-0"></logo-default>
        </a>
        <h2
          class="mt-6 text-center text-3xl leading-9 font-bold text-gray-900"
        >Sign in to your accounts</h2>
        <p class="mt-2 text-center text-sm leading-5 text-gray-600 max-w">
          Or
          <inertia-link
            class="font-medium text-orange-500 focus:outline-none focus:underline transition ease-in-out duration-150"
            :href="$route('sign-up')"
          >start your 14-day free trial</inertia-link>
        </p>
      </div>

      <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div v-if="$page.flash.info">
          <div>
            <alert-info class="mb-3 shadow" text="We found an account with this email address."></alert-info>
          </div>
        </div>

        <div v-if="$page.errors" class="red">
          <div>
            <alert-danger
              class="mb-3 shadow"
              title="Whoops!"
              text="These credentials don't match our records"
            ></alert-danger>
          </div>
        </div>

        <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
          <form @submit.prevent :action="$route('login')" method="POST">
            <csrf></csrf>

            <form-input
              id="email"
              name="email"
              type="email"
              :value="$page.old.email"
              v-model="form.email"
              required
              label="Email address"
            ></form-input>

            <form-input
              class="pt-2"
              id="password"
              name="password"
              v-model="form.password"
              type="password"
              required
              label="Password"
            ></form-input>

            <div class="mt-6 flex items-center justify-between">
              <form-checkbox
                v-model="form.remember"
                id="remember"
                name="remember"
                label="Remember me"
              ></form-checkbox>

              <div class="text-sm leading-5">
                <inertia-link
                  :href="$route('password.request')"
                  class="font-medium text-orange-500 hover:text-orange-500 focus:outline-none focus:underline transition ease-in-out duration-150"
                >Forgot your password?</inertia-link>
              </div>
            </div>

            <div class="mt-6">
              <button-primary @click="submit" text="Sign in" type="submit"></button-primary>
            </div>
          </form>

          <div class="mt-4">
            <div class="relative">
              <div class="absolute inset-0 flex items-center">
                <div class="w-full border-t border-gray-300"></div>
              </div>
              <div class="relative flex justify-center text-sm leading-5">
                <span class="px-2 bg-white text-gray-500">Or continue with</span>
              </div>
            </div>

            <div>
              <button-github
                :route="$route('github.redirect',{action:'login'})"
                text="GitHub"
                class="mt-3"
              ></button-github>
            </div>
          </div>
        </div>
      </div>
    </div>
  </layout>
</template>

<script>
import Layout from "../../layouts/public";

export default {
  components: {
    Layout,
  },
  data() {
    return {
      form: {
        email: this.$page.old.email ? this.$page.old.email : "",
        password: null,
        remember: null,
      },
    };
  },
  methods: {
    submit() {
      this.$inertia.post(this.$route("login"), this.form);
    },
  },
};
</script>

<style scoped>
</style>
