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
        <!--
        Slide-over panel, show/hide based on slide-over state.

        Entering: "transform transition ease-in-out duration-500 sm:duration-700"
          From: "translate-x-full"
          To: "translate-x-0"
        Leaving: "transform transition ease-in-out duration-500 sm:duration-700"
          From: "translate-x-0"
          To: "translate-x-full"
      -->
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
            @submit.prevent="
              form.put($route('indexing.plan.update', { plan: planId }))
            "
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
                      <!-- Heroicon name: x -->
                      <svg
                        class="h-6 w-6"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        aria-hidden="true"
                      >
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="2"
                          d="M6 18L18 6M6 6l12 12"
                        />
                      </svg>
                    </button>
                  </div>
                </div>
              </div>
              <div class="flex-1 flex flex-col justify-between">
                <div class="px-4 divide-y divide-gray-200 sm:px-6">
                  <div class="space-y-6 pt-6 pb-5">
                    <div>
                      <form-input
                        class="pt-2"
                        id="name"
                        type="text"
                        v-model="form.name"
                        :errors="form.errors.name"
                        required
                        label="Name"
                      ></form-input>
                      <form-select
                        class="pt-2"
                        id="type"
                        v-model="form.type"
                        :errors="form.errors.type"
                        required
                        :items="{
                          file: { name: 'File', id: 'file' },
                        }"
                        label="Type"
                      ></form-select>
                      <form-textarea
                        id="description"
                        name="description"
                        v-model="form.description"
                        :errors="form.errors.description"
                        label="Description"
                      >
                      </form-textarea>
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
        this.form.reset();
      }
    },
  },
  data() {
    return {
      show: this.showForm,
      hidden: true,
      planId: null,
      form: this.$inertia.form({
        type: "",
        cluster_id: this.clusterId,
        name: "",
        description: "",
      }),
    };
  },
  methods: {
    edit(plan) {
      this.planId = plan.id;
      this.form.type = plan.type;
      this.form.name = plan.name;
      this.form.description = plan.description;
    },
    inertiaToVuelidate() {
      return {
        $anyError: form.errors.name,
        $pending: form.processing,
        $dirty: true,
      };
    },
    hideForm() {
      this.show = false;

      delay(() => {
        this.hidden = true;

        this.$emit("hide");
      }, 700);
    },
  },
};
</script>

<style scoped>
</style>
