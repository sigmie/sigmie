<template>
  <div class="row">
    <div class="col-md-6 col-sm-6 col-xs-12 last-xs first-md first-sm">
      <div class="box md:float-right">
        <register-form
          :intent="app.intent"
          :action="formAction"
          :privacy-route="privacyRoute"
          :terms-route="termsRoute"
        />
      </div>
    </div>
    <div class="col-md-6 col-xs-12">
      <div class="box">
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
