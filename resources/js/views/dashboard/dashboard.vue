<template>
  <app title="Dashboard">
    <div v-if="state === 'loaded'">
      <creating
        class="pt-4"
        v-if="cluster.state === 'queued_create' || cluster.state === 'created'"
      ></creating>

      <destroying
        class="pt-4"
        v-if="cluster.state === 'queued_destroy'"
      ></destroying>

      <running
        v-if="cluster.state === 'running'"
        :clusterInfo="cluster.info"
        :indices="cluster.indices"
      ></running>

      <destroyed
        v-if="cluster.state === 'destroyed'"
        :clusterId="clusterId"
        class="max-w-md mx-auto"
      ></destroyed>

      <none v-if="cluster.state === null" class="max-w-md mx-auto"></none>
    </div>
    <div v-else-if="state === 'error'" class="mx-auto max-w-sm">
      <error></error>
    </div>
  </app>
</template>

<script>
import App from "../layouts/app";
import delay from "lodash/delay";
import throttle from "lodash/throttle";

import creating from "./_creating";
import error from "./_error";
import destroying from "./_destroying";
import running from "./_running";
import destroyed from "./_destroyed";
import none from "./_none";

export default {
  props: ["clusterId"],
  components: {
    App,
    creating,
    destroying,
    running,
    destroyed,
    none,
  },
  data() {
    return {
      state: "empty",
      cluster: {
        state: null,
        id: null,
        indices: null,
        clusterInfo: null,
      },
    };
  },
  mounted() {
    this.loadData();
  },
  methods: {
    loadData() {
      let response = this.$http
        .get(this.$route("dashboard.data", { project: this.$page.props.project_id }))
        .then((response) => {
          let data = response.data;

          this.$set(this.cluster, "state", data.clusterState);
          this.$set(this.cluster, "id", data.clusterId);
          this.$set(this.cluster, "indices", data.indices);
          this.$set(this.cluster, "info", data.clusterInfo);

          this.state = "loaded";
        })
        .catch((e) => {
          this.state = "error";
        });

      setTimeout(this.loadData, 6000000);
    },
  },
  beforeMount() {
    throttle(() => {
      this.$inertia.reload({
        method: "get",
        data: {},
        preserveState: false,
        preserveScroll: false,
        only: ["indices", "clusterInfo"],
      });
    }, 30000);

    if (this.clusterId === null) {
      return;
    }

    this.$socket
      .private(`cluster.${this.clusterId}`)
      .listen("Cluster\\ClusterWasBooted", (e) => {
        // Wait 5 seconds for the cluster state to change
        // in the database before reloading the page
        delay(() => {
          this.$inertia.reload({
            method: "get",
            data: {},
            preserveState: false,
            preserveScroll: false,
            only: [],
          });
        }, 5);
      });

    this.$socket
      .private(`cluster.${this.clusterId}`)
      .listen("Cluster\\ClusterWasDestroyed", (e) => {
        // Wait 5 seconds for the cluster state to change
        // in the database before reloading the page
        delay(() => {
          this.$inertia.reload({
            method: "get",
            data: {},
            preserveState: false,
            preserveScroll: false,
            only: [],
          });
        }, 5);
      });
  },
};
</script>

<style>
</style>
