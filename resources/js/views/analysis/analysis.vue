<template>
  <app>
    <navigation></navigation>

    <form-select
      @change="changeIndex"
      label="Index"
      name="name"
      id="id"
      aria-label="Index"
      displayKey="name"
      :items="indicesNames"
    ></form-select>

    <component
      :data="data"
      :indexName="indexName"
      :indices="indices"
      :is="section"
    ></component>

  </app>
</template>

<script>
import App from "../layouts/app";
import pagination from "./_pagination";
import navigation from "./_navigation";
import stopwords from "./_stopwords";
import mapping from "./_mapping";
import forEach from "lodash/forEach";

export default {
  props: ["data", "section", "indices"],
  methods: {
    changeIndex(index) {
      const route = this.$route(this.$route().current());
      this.$inertia.get(route, {
        index: index.id,
      });
    },
  },
  mounted() {
    let indicesNames = {};

    forEach(this.indices, (indexData, index) => {
      indicesNames[index] = { id: indexData.name, name: indexData.name };
    });

    this.indicesNames = indicesNames;

    this.indexName = this.$route().params["index"];
  },
  data() {
    return {
      indicesNames: {},
      indexName: null,
    };
  },
  components: {
    App,
    pagination,
    navigation,
    mapping,
    stopwords
  },
};
</script>

<style scoped>
</style>
