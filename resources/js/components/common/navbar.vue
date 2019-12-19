<template>
  <div class="m-0 p-3 row flex h-full m-auto">
    <div class="col-md-4 align-middle">
      <input id="search" name="search" type="search" class="align-middle" />
    </div>

    <div class="col-md-4 col-md-offset-4 flex flex-col justify-center">
      <div class="flex justify-end">
        <a v-if="auth" class="cursor-pointer" @click.prevent="logout">Logout</a>

        <form v-if="auth" id="logout-form" :action="logoutAction" method="POST" class="hidden">
          <csrf />
        </form>

        <a v-if="!auth" class="pl-4" :href="loginRoute">Login</a>
        <a v-if="!auth" class="pl-4" :href="registerRoute">Register</a>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  props: ["logoutAction", "registerRoute", "loginRoute", "auth"],
  methods: {
    logout() {
      document.getElementById("logout-form").submit();
    }
  }
};
</script>
