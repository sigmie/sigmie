<template>
  <layout title="Password reset">
    <div class="min-h-screen flex flex-col justify-center sm:px-6 lg:px-8" v-cloak>
      <div class="sm:mx-auto sm:w-full sm:max-w-md pb-6">
        <div class="mx-auto pb-5">
          <a :href="$route('landing')">
            <logo-default class="mx-3 sm:mx-0"></logo-default>
          </a>
        </div>

        <div v-if="$page.errors" class="pt-4">
          <alert-danger
            class="shadow"
            title="Whoops!"
            text="These credentials do not match our records"
          />
        </div>

        <div v-if="sent" class="pt-4">
          <alert-success
            class="shadow"
            title="Nice!"
            text="Check your email for a link to reset your password"
          />
        </div>
      </div>

      <div class="mx-auto bg-white shadow rounded-lg py-6 px-4 max-w-md flex flex-col w-full">
        <form
          @submit.prevent
          :action="$route('password.email')"
          method="POST"
          class="flex flex-col w-full px-4"
        >
          <span
            class="text-gray-500 pb-6"
          >Enter the email associated with your account and you will get a link to reset your password.</span>
          <div class="pb-6">
            <form-input
              label="Email address"
              placeholder="john@yahoo.com"
              id="email"
              v-model="email"
              name="email"
              type="email"
              :value="$page.old.email"
              required
            />
          </div>

          <div>
            <button-primary @click="submit" text="Send" type="submit" />
          </div>
        </form>
      </div>
    </div>
  </layout>
</template>

<script>
import Layout from "../../layouts/public";

export default {
  props: ["sent"],
  components: {
    Layout,
  },
  data() {
    return {
      email: null,
    };
  },
  methods: {
    async submit() {
      let email = this.email;

      await this.$inertia.post(this.$route("password.email"), { email });

      this.email = null;
    },
  },
};
</script>

<style scoped>
</style>
