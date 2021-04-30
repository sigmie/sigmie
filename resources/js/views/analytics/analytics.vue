<template>
  <app>

<div class="flex flex-col space-y-4">
      <settings-bar></settings-bar>

      <requests></requests>

      <stats></stats>

<div class="flex justify-between space-x-2">
    <top-results></top-results>
    <top-searches></top-searches>
    <without-results></without-results>
</div>

      </div>
  </app>
</template>

<script>
import App from "../layouts/app";
import requests from "./_requests";
import stats from "./_stats";
import settingsBar from "./_settings-bar";
import topResults from "./_top-results";
import topSearches from "./_top-searches";
import withoutResults from "./_without-results";

export default {
  components: {
    App,
    requests,
    stats,
    topResults,
    withoutResults,
    topSearches,
    settingsBar
  },
  mounted() {
  },
};
</script>

<style scoped>
</style>
