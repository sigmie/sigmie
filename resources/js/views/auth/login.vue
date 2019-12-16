<template>
  <div class="h-full mx-auto">
    <form
      class="mx-auto flex container w-84 text-gray-700 h-auto"
      method="POST"
      :action="formAction"
    >
      <csrf />
      <div
        class="container flex justify-center w-auto block border-gray-200 border rounded bg-white px-4"
      >
        <div class="row">
          <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-8 border-b">
            <heading-form text="Login" />
          </div>
          <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-8 pt-4">
            <form-input
              :type="'text'"
              id="email-field"
              :value="old.email"
              name="email"
              label="Email"
            />
          </div>
          <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-8 pt-4 pb-6">
            <form-input
              id="password-field"
              :type="'password'"
              value
              name="password"
              label="Password"
            />
            <a class="text-gray-500 text-sm py-1" :href="passwordResetRoute">Forgot Your Password?</a>
          </div>

          <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 bg-gray-300 px-8">
            <div class="float-left box w-full py-3">
              <div class="container">
                <div class="w-full">
                  <button-primary type="submit" text="Login" />
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </form>
  </div>
</template>

<script>
export default {
  props: ["formAction", "passwordResetRoute", "errors", "old", "app"],
  components: {
    "login-form": require("../../components/auth/login/form").default
  }
};
</script>
