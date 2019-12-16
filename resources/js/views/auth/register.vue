<template>
  <div class="row">
    <div class="col-md-6 col-sm-12 col-xs-12 last-xs last-sm first-md">
      <div class="box lg:float-right sm:float-none sm:mx-auto">
        <register-form
          :intent="app.intent"
          :action="formAction"
          :privacy-route="privacyRoute"
          :terms-route="termsRoute"
        />
      </div>
    </div>
    <div class="col-md-6 col-sm-12 col-xs-12">
      <div class="box sm:mx-auto pb-6 lg:pb-0">
        <plan />
      </div>
    </div>
  </div>
</template>

<script>
export default {
  props: ["formAction", "termsRoute", "privacyRoute", "errors", "old", "app"],
  components: {
    "register-form": require("../../components/auth/register/form").default,
    plan: require("../../components/auth/register/plan").default
  }
};
</script>
