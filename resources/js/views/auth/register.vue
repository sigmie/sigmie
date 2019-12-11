<template>
  <div class="row">
    <div class="col-md-6">
      <register-form intent="app.intent" action="app.route" />
    </div>
    <div class="col-md-6">plan</div>
  </div>
</template>

<script>
export default {
  props: ["app"],
  components: {
    'register-form': require("../../components/auth/register/form").default
  }
};
</script>
