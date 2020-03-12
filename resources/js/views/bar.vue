<template>
  <p>Bar</p>
</template>

<script>
import View from "./View";

var component = View.extend({
  beforeMount() {
    var self = this;
    this.$http.post("/bar").then(function(response) {
      self.state = "fetched";
    });
  }
});
export default component;
</script>

<style>
</style>
