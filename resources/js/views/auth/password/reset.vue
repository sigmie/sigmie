<template>
  <public>
    <div class="min-h-screen flex flex-col justify-center py-12 sm:px-6 lg:px-8" v-cloak>
      <div class="sm:mx-auto sm:w-full sm:max-w-md pb-4">
        <div class="mx-auto">
          <inertia-link :href="$route('landing')">
            <logo-default></logo-default>
          </inertia-link>
        </div>

        <div v-if="$page.errors" class="pt-4">
          <alert-danger
            class="mb-3 shadow"
            title="Whoops!"
            v-if="$page.errors.email"
            :text="$page.errors.email[0]"
          ></alert-danger>
        </div>

        <div v-if="reset" class="pt-4">
          <alert-success
            class="mb-3 shadow"
            title="Nice!"
            text="Check your email for a link to reset your password"
          ></alert-success>
        </div>
      </div>

      <container-white class="mx-auto py-6 px-4 w-full max-w-md flex flex-col w-full">
        <password-form :token="token"></password-form>
      </container-white>
    </div>
  </public>
</template>

<script>
import Public from "../../layouts/public";

export default {
  props: ["token", "reset"],
  components: {
    Public,
    passwordForm: require("./form").default
  }
};
</script>

<style scoped>
</style>
