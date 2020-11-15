<template>
  <app title="Create Cluster">
    <confirmation
      @confirm="submit"
      @cancel="showConfirmation = false"
      @clickAway="showConfirmation = false"
      @onEsc="showConfirmation = false"
      :show="showConfirmation"
      :cluster="{
        nodes: nodes,
        cores: cores,
        url: `https://${name}.sigmie.app`,
        memory: memoryInGb,
        disk: disk,
        data_center: dataCenter ? dataCenter.name : null,
        name:name,
        version: version
      }"
    ></confirmation>

    <div class="max-w-4xl mx-auto">
      <div class="flex md:items-center md:justify-between md:mb-4">
        <div class="flex-1 min-w-0"> <h2
            class="text-lg font-bold leading-7 text-gray-900 sm:text-3xl sm:leading-9 sm:truncate"
          >
            Add new cluster
          </h2>
        </div>
        <!-- <div class="flex-1 md:mt-0 md:ml-4">
          <a
            class="tracking-wide text-sm text-gray-400 float-right cursor-not-allowed"
            href="#"
            >Use existing</a
          >
        </div> -->
      </div>

      <name
        @nameChange="(value) => set('name', value)"
        @validate="(invalid) => (this.sections.name.invalid = invalid)"
      ></name>

      <separator></separator>

      <specs
        :regions="regions"
        @memoryInGBWasUpdated="(value) => this.memoryInGb = value"
        @memoryChange="(value) => set('memory', value)"
        @coresChange="(value) => set('cores', value)"
        @versionChange="(value) => set('version', value)"
        @diskChange="(value) => set('disk', value)"
        @nodesChange="(value) => set('nodes', parseFloat(value))"
        @dataCenterChange="(value) => set('dataCenter', value)"
        @validate="(invalid) => (this.sections.specs.invalid = invalid)"
      ></specs>

      <separator></separator>

      <security
        @usernameChange="(value) => set('username', value)"
        @passwordChange="(value) => set('password', value)"
        @validate="(invalid) => (this.sections.security.invalid = invalid)"
        :submitDisabled="sections.name.invalid || sections.specs.invalid || sections.security.invalid"
        @submit="showConfirmation = true"
      ></security>
    </div>
  </app>
</template>

<script>
import App from "../../layouts/app";
import name from "./_name";
import separator from "../_shared/_separator";
import specs from "../_shared/_specs";
import security from "../_shared/_security";
import confirmation from "../_shared/_confirmation";

export default {
  props: ["regions"],
  components: {
    App,

    separator,
    name,
    specs,
    security,
    confirmation,
  },
  data() {
    return {
      sections: {
        name: {
          invalid: true,
        },
        specs: {
          invalid: true,
        },
        security: {
          invalid: true,
        },
      },
      memory: 2048,
      cores: 1,
      showConfirmation: false,
      memoryInGb: 1,
      name: "",
      disk: 10,
      version: "",
      dataCenter: null,
      username: "",
      password: "",
      nodes: 3,
    };
  },
  methods: {
    submit() {
      let cluster = {
        nodes_count: this.nodes,
        username: this.username,
        password: this.password,
        cores: this.cores,
        memory: this.memory,
        disk: this.disk,
        region_id: this.dataCenter.id,
        name: this.name,
        project_id: this.$page.project_id,
      };

      this.$inertia.post("/cluster", cluster);
    },
    set(key, value) {
      this[key] = value;
    },
  },
};
</script>

<style>
</style>
