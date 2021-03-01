<template>
  <app title="Settings">
    <div
      class="shadow mx-auto rounded-md sm:overflow-hidden max-w-lg bg-white px-6 py-5"
    >
      <div class="">
        <h3 class="text-lg leading-6 font-medium text-gray-900">Settings</h3>
        <p class="text-sm text-gray-500">Your project setttings.</p>
        <form
          @submit.prevent="submit"
          class="mt-6 sm:mt-5 space-y-6 sm:space-y-5"
        >
          <div
            class="sm:grid sm:grid-cols-3 sm:gap-4 sm:items-start sm:border-t sm:border-gray-200 sm:pt-5"
          >
            <label
              for="name"
              class="block text-sm font-medium text-gray-700 sm:mt-px sm:pt-2"
            >
              Name
            </label>
            <div class="mt-1 sm:mt-0 sm:col-span-2">
              <form-input
                id="name"
                type="text"
                v-model="form.name"
                :errors="form.errors.name"
              ></form-input>
            </div>
          </div>

          <div
            class="sm:grid sm:grid-cols-3 sm:gap-4 sm:items-start sm:border-t sm:border-gray-200 sm:pt-5"
          >
            <label
              for="description"
              class="block text-sm font-medium text-gray-700 sm:mt-px sm:pt-2"
            >
              Description
            </label>
            <div class="mt-1 sm:mt-0 sm:col-span-2">
              <form-textarea
                id="description"
                name="description"
                v-model="form.description"
                :errors="form.errors.description"
              ></form-textarea>
            </div>
          </div>

          <div class="flex flex-row items-center justify-end">
            <p
              v-if="form.processing"
              class="text-sm text-gray-500 py-2 mr-5 float-right"
            >
              Saving...
            </p>
            <p
              v-if="form.recentlySuccessful"
              class="mr-3 flex items-center text-sm text-gray-500 float-right"
            >
              <svg
                class="mr-1 h-5 w-5 text-green-400"
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 20 20"
                fill="currentColor"
                aria-hidden="true"
              >
                <path
                  fill-rule="evenodd"
                  d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                  clip-rule="evenodd"
                />
              </svg>
              Saved!
            </p>

            <div class="w-auto flex">
              <button-secondary
                :disabled="form.processing"
                type="submit"
                text="Save"
              ></button-secondary>
            </div>
          </div>
        </form>
      </div>
    </div>

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
import App from "../../layouts/app";

export default {
  components: {
    App,
  },
  props: ["clusterId", "clusterState", "project"],
  data() {
    return {
      form: this.$inertia.form({
        name: this.project.name,
        description: this.project.description,
      }),
      showDestroy: false,
    };
  },
  methods: {
    submit() {
      let route = this.$route("project.update", { project: this.project.id });

      this.form
        .transform((data) => {
          return data;
        })
        .put(route);
    },
    destroy() {
      this.$inertia.delete(this.$route("cluster.destroy", this.clusterId));
    },
  },
};
</script>

<style scoped>
</style>
