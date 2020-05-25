<template>
  <public>
    <div class="min-h-screen flex flex-col justify-center py-12 sm:px-6 lg:px-8" v-cloak>
      <div class="sm:mx-auto sm:w-full sm:max-w-md pb-6">
        <div class="mx-auto pb-2">
          <a :href="$route('landing')">
            <logo-default />
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

      <container-white class="mx-auto py-6 px-4 w-full max-w-md flex flex-col w-full">
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
      </container-white>
    </div>
  </public>
</template>

<script>
import Public from "../../layouts/public";

export default {
  props: ["sent"],
  components: {
    Public
  },
  data() {
    return {
      email: null
    };
  },
  methods: {
    submit() {
      let email = this.email;

      this.$inertia.post(this.$route("password.email"), { email });
    }
  }
};
</script>

<style scoped>
</style>
