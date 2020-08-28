<template>
  <app title="Settings">
    <div class="shadow mx-auto sm:rounded-md sm:overflow-hidden max-w-xl">
      <form class="px-4 py-5 bg-white rounded-t-md">
        <div>
          <!-- <div>
            <div>
              <h3 class="text-lg leading-6 font-medium text-gray-900">Settings</h3>
            </div>
          </div>-->
          <div>
            <div class>
              <fieldset class>
                <legend class="text-base font-medium text-red-700">Danger zone</legend>
                <div class="pt-5 mt-3 border-t border-gray-200 w-full">
                  <div class="flex justify-between">
                    <div class>
                      <div class="font-semibold text-base text-gray-800">Destroy this cluster</div>
                      <div class="text-sm text-gray-600">This will destroy your production cluster.</div>
                    </div>
                    <div class="max-w-sm py-1">
                      <button-danger
                        :disabled="clusterId === null"
                        id="destroy_cluster"
                        @click="showDestroy = true"
                        :text="clusterId === null ?  'Destroyed': 'Destroy'"
                      ></button-danger>
                    </div>
                  </div>
                </div>
              </fieldset>
            </div>
          </div>
        </div>
      </form>
    </div>

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
  </app>
</template>

<script>
import App from "../layouts/app";

export default {
  components: {
    App
  },
  props: ["clusterId"],
  data() {
    return {
      showDestroy: false
    };
  },
  methods: {
    destroy() {
      this.$inertia.delete(
        this.$route("cluster.destroy", this.clusterId)
      );
    }
  }
};
</script>

<style scoped>
</style>
