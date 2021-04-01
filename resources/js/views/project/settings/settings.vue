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

    <basicauth :cluster="cluster"> </basicauth>

    <addresses
      :disabled="!cluster.can_update_allowed_ips"
      v-if="cluster.has_allowed_ips"
      :clusterId="cluster.id"
      :ips="cluster.allowedIps"
    ></addresses>

    <danger
      v-if="cluster.can_be_destroyed"
      :clusterState="cluster.state"
      :clusterId="cluster.id"
    ></danger>
  </app>
</template>

<script>
import App from "../../layouts/app";
import Danger from "./danger";
import Addresses from "./addresses";
import Basicauth from "./basicauth";
import delay from "lodash/delay";

export default {
  components: {
    App,
    Addresses,
    Basicauth,
    Danger,
  },
  props: ["cluster", "project"],
  data() {
    return {
      form: this.$inertia.form({
        name: this.project.name,
        description: this.project.description,
      }),
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
  },
  mounted() {
    // Delay the reload because if the update is done
    // on the same page then the panel is not closing
    // and the controller redirect isnt' taken into
    // consideration
    this.$socket
      .private(`cluster.${this.cluster.id}`)
      .listen(".cluster.updated", (e) => {
        delay(() => {
          this.$inertia.reload({ only: ["cluster"] });
        }, 1000);
      });
  },
};
</script>

<style scoped>
</style>
