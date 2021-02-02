<template>
  <div
    :class="hidden ? 'hidden' : 'block'"
    class="fixed inset-0 overflow-hidden"
  >
    <div class="absolute inset-0 overflow-hidden">
      <section
        class="absolute inset-y-0 pl-16 max-w-full right-0 flex"
        aria-labelledby="slide-over-heading"
      >
        <div
          v-on-clickaway="show ? hideForm : () => null"
          :class="
            show
              ? 'transform transition ease-in-out duration-500 sm:duration-700 translate-x-0'
              : 'transform transition ease-in-out duration-500 sm:duration-700 translate-x-full'
          "
          class="w-screen max-w-md"
        >
          <form
            @submit.prevent="submit"
            class="h-full pt-16 divide-y divide-gray-200 flex flex-col bg-white shadow-xl"
          >
            <div class="flex-1 h-0 overflow-y-auto">
              <div class="py-6 px-4 sm:px-6 bg-gray-50">
                <div class="flex items-center justify-between">
                  <h2
                    id="slide-over-heading"
                    class="text-lg font-medium text-gray-900"
                  >
                    Update Plan
                  </h2>
                  <div class="ml-3 h-7 flex items-center">
                    <button
                      @click="hideForm"
                      class="rounded-md text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-white"
                    >
                      <span class="sr-only">Close panel</span>

                      <icon-x class="h-6 text-gray-500"></icon-x>
                    </button>
                  </div>
                </div>
              </div>
              <div class="flex-1 flex flex-col justify-between">
                <div class="px-4 divide-y divide-gray-200 sm:px-6">
                  <div class="space-y-6 pt-6 pb-5">
                    <div class="space-y-3">
                      <form-input
                        id="name"
                        type="text"
                        v-model="form.name"
                        :errors="form.errors.name"
                        label="Name"
                      ></form-input>

                      <form-textarea
                        id="description"
                        name="description"
                        v-model="form.description"
                        :errors="form.errors.description"
                        label="Description"
                      >
                      </form-textarea>

                      <div class="relative my-6">
                        <div
                          class="absolute inset-0 flex items-center"
                          aria-hidden="true"
                        >
                          <div class="w-full border-t border-gray-300"></div>
                        </div>
                        <div class="relative flex justify-center">
                          <span class="px-2 bg-white text-sm text-gray-500">
                            Details
                          </span>
                        </div>
                      </div>

                      <form-input
                        id="index_alias"
                        type="text"
                        v-model="form.index_alias"
                        :errors="form.errors['type.index_alias']"
                        label="Index alias"
                      ></form-input>

                      <form-input
                        v-if="form.type === 'file'"
                        id="location"
                        type="text"
                        v-model="form.location"
                        :errors="form.errors['type.location']"
                        placeholder="https://example.com"
                        label="File Location"
                      ></form-input>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div
              class="flex-shrink-0 px-4 py-4 flex space-x-4 justify-end w-full"
            >
              <button-primary
                :disabled="form.processing"
                type="submit"
                text="Save"
              ></button-primary>
              <button-secondary
                @click="hideForm"
                text="Cancel"
              ></button-secondary>
            </div>
          </form>
        </div>
      </section>
    </div>
  </div>
</template>

<script>
import delay from "lodash/delay";

export default {
  props: ["showForm", "clusterId", "plan"],
  watch: {
    showForm(newVal, oldVal) {
      this.hidden = !newVal;

      delay(() => {
        this.show = newVal;
      }, 5);
    },
    "form.recentlySuccessful": function (newVal, oldVal) {
      if (newVal === true) {
        this.hideForm();
      }
    },
  },
  data() {
    return {
      show: this.showForm,
      hidden: true,
      planId: null,
      form: this.$inertia.form({
        type: "file",
        location: "",
        index_alias: "",
        cluster_id: this.clusterId,
        name: "",
        description: "",
      }),
    };
  },
  methods: {
    edit(plan) {
      this.planId = plan.id;

      this.form.name = plan.name;

      this.form.type = plan.type_type;
      this.form.index_alias = plan.type.index_alias;
      this.form.location = plan.type.location;
    },
    inertiaToVuelidate() {
      return {
        $anyError: form.errors.name,
        $pending: form.processing,
        $dirty: true,
      };
    },
    submit() {
      let route = this.$route("indexing.plan.update", { plan: this.planId });

      return this.form
        .transform((data) => {
          data.type = {
            type: data.type,
            location: data.location,
            index_alias: data.index_alias,
          };

          return data;
        })
        .put(route);
    },
    hideForm() {
      this.show = false;

      delay(() => {
        this.hidden = true;

        this.form.reset();

        this.$emit("hide");
      }, 700);
    },
  },
};
</script>

<style scoped>
</style>
