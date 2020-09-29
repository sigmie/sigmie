<template>
  <layout title="Sign Up">
    <div class="flex justify-center mx-auto">
      <div class="flex flex-col md:p-10 md:flex-row justify-between md:space-x-14 pt-15">
        <div class="flex justify-center">
          <div class="self-center">
            <div class="sm:mx-auto sm:w-full sm:max-w-md pt-5">
              <a :href="$route('landing')" class="mx-auto">
                <logo-default class="h-20 mx-auto mb-10"></logo-default>
              </a>
            </div>

            <register-form
              class="w-80"
              id="register"
              method="POST"
              :action="$route('register')"
              :github-route="$route('github.redirect', {action :'register'})"
              :github-user="githubUser"
            />
            <div class="text-gray-500 text-base antialiased mt-5 mx-auto text-center">
              Already having an account ?
              <inertia-link class="text-orange-400 font-semibold" :href="$route('sign-in')">Sign-in</inertia-link>
            </div>
          </div>
        </div>

        <plan></plan>
      </div>
    </div>
  </layout>
</template>

<script>
import Layout from "../layouts/public";

export default {
  components: {
    Layout,
    registerForm: require("./register/form").default,
    plan: require("./register/plan").default,
  },
  props: ["githubUser" ],
  methods: {},
};
</script>

<style scoped>
</style>
