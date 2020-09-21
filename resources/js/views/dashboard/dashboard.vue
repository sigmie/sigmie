<template>
  <app title="Dashboard">
    <creating class="pt-4" v-if="clusterState === 'queued_create' || clusterState === 'created'"></creating>

    <destroying class="pt-4" v-if="clusterState === 'queued_destroy'"></destroying>

    <running v-if="clusterState === 'running'" :clusterInfo="clusterInfo" :indices="indices"></running>

    <destroyed v-if="clusterState === 'destroyed'" :clusterId="clusterId" class="max-w-md mx-auto"></destroyed>

    <null v-if="clusterState === null" class="max-w-md mx-auto"></null>
  </app>
</template>

<script>
import App from "../layouts/app";
import delay from "lodash/delay";
import throttle from "lodash/throttle";
import loginVue from "../auth/login.vue";

export default {
  props: [],
  components: {
    App,
    creating: require("./_creating").default,
    destroying: require("./_destroying").default,
    running: require("./_running").default,
    destroyed: require("./_destroyed").default,
    null: require("./_null").default,
  },
  data() {
    return {
      clusterState: null,
      clusterId: null,
      indices: null,
      clusterInfo: null,
    };
  },
  mounted() {
    this.loadData();
  },
  methods: {
    async loadData() {
      let response = await this.$http.get(
        this.$route("dashboard.data", { project: this.$page.project_id })
      );

      console.log(response);

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
      .listen("ClusterWasBooted", (e) => {
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
      .listen("ClusterWasDestroyed", (e) => {
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
