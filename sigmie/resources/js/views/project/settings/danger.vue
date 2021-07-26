<template>
  <div
    class="px-6 py-5 shadow mx-auto bg-white rounded-md sm:overflow-hidden max-w-lg mt-6"
  >
    <fieldset class>
      <legend class="text-base font-medium text-red-700">Danger zone</legend>
      <div class="pt-5 mt-3 border-t border-gray-200 w-full">
        <div class="flex justify-between">
          <div class>
            <div class="font-semibold text-base text-gray-800">
              Destroy this cluster
            </div>
            <div class="text-sm text-gray-600">
              This will destroy your production cluster.
            </div>
          </div>
          <div class="max-w-sm py-1">
            <button-danger
              :disabled="clusterId === null || clusterState !== 'running'"
              id="destroy_cluster"
              @click="showDestroy = true"
              text="Destroy"
            ></button-danger>
          </div>
        </div>
      </div>
    </fieldset>

    <modal
      title="Destroy cluster ?"
      content="Are you sure you want to destroy your cluster? All of your cluster data will be permanently lost forever. This action cannot be undone."
      primaryText="Destroy"
      secondaryText="Cancel"
      @primaryAction="destroy"
      @secondaryAction="showDestroy = false"
      @clickAway="showDestroy = false"
      @onEsc="showDestroy = false"
      :icon="true"
      :show="showDestroy"
      type="danger"
    ></modal>
  </div>
</template>

<script>
export default {
  props: ["clusterId", "clusterState"],
  data() {
    return {
      showDestroy: false,
    };
  },
  methods: {
    destroy() {
      this.$inertia.delete(this.$route("cluster.destroy", this.clusterId));
    },
  },
};
</script>

<style scoped>
</style>
