<template>
  <app title="Restore Cluster">
    <confirmation
      @confirm="submit"
      @cancel="showConfirmation = false"
      @clickAway="showConfirmation = false"
      @onEsc="showConfirmation = false"
      :show="showConfirmation"
      :cluster="{
        nodes: nodes,
        cores: cores,
        url: `https://${cluster.name}.sigmie.app`,
        memory: memoryInGb,
        disk: disk,
        data_center: dataCenter ? dataCenter.name : null,
        name: cluster.name,
        version: version
      }"
    ></confirmation>

    <div class="max-w-4xl mx-auto">
      <div class="flex md:items-center md:justify-between md:mb-4">
        <div class="flex-1 min-w-0">
          <h2
            class="text-lg font-bold leading-7 text-gray-900 sm:text-3xl sm:leading-9 sm:truncate"
          >
            Restore cluster
          </h2>
          <p class="text-sm text-gray-400">
            You are editing
            <span class="text-orange-500"
              >https://{{ cluster.name }}.sigmie.app</span
            >
          </p>
        </div>
      </div>

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
        :submitDisabled="sections.specs.invalid || sections.security.invalid"
        @submit="showConfirmation = true"
      ></security>
    </div>
  </app>
</template>

<script>
import App from "../../layouts/app";
import separator from "../_shared/_separator";
import specs from "../_shared/_specs";
import security from "../_shared/_security";
import confirmation from "../_shared/_confirmation";

export default {
  components: {
    App,
    separator,
    specs,
    security,
    confirmation,
  },
  props: ["cluster", "regions"],
  data() {
    return {
      sections: {
        specs: {
          invalid: true,
        },
        security: {
          invalid: true,
        },
      },
      memoryInGb: 1,
      memory: 2048,
      disk: 10,
      cores: 1,
      showConfirmation: false,
      dataCenter: null,
      username: "",
      password: "",
      version: "",
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
        disk: this.disk,
        memory: this.memory,
        region_id: this.dataCenter.id,
        project_id: this.$page.project_id,
      };

      this.$inertia.put(`/cluster/${this.cluster.id}`, cluster);
    },
    set(key, value) {
      this[key] = value;
    },
  },
};
</script>

<style>
</style>
