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
          v-on-clickaway="show ? hideForm : null"
          :class="
            show
              ? 'transform transition ease-in-out duration-500 sm:duration-700 translate-x-0'
              : 'transform transition ease-in-out duration-500 sm:duration-700 translate-x-full'
          "
          class="w-screen max-w-md"
        >
          <form
            @submit.prevent="form.post($route('indexing.plan.store'))"
            class="h-full pt-14 divide-y divide-gray-200 flex flex-col bg-white shadow-xl"
          >
            <div class="flex-1 h-0 overflow-y-auto">
              <div class="py-6 px-4 bg-theme-gray-600 sm:px-6">
                <div class="flex items-center justify-between">
                  <h2 id="slide-over-heading" class="text-lg font-medium">
                    New Plan
                  </h2>
                  <div class="ml-3 h-7 flex items-center">
                    <button
                      @click="hideForm"
                      class="rounded-md text-gray-600 hover:text-white focus:outline-none focus:ring-2 focus:ring-white"
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
                        required
                        label="Name"
                      ></form-input>
                      <form-input
                        class="pt-2"
                        id="name"
                        type="text"
                        v-model="form.type"
                        required
                        label="type"
                      ></form-input>

                      <form-input
                        class="pt-2"
                        id="name"
                        type="text"
                        v-model="form.cluster_id"
                        required
                        label="Cluster id"
                      ></form-input>
                    </div>
                    <div>
                      <form-textarea
                        v-model="form.description"
                        id="description"
                        name="description"
                        label="Description"
                      >
                      </form-textarea>
                    </div>
                    <fieldset>
                      <legend class="text-sm font-medium text-gray-900">
                        Privacy
                      </legend>
                      <div class="mt-2 space-y-5">
                        <div class="relative flex items-start">
                          <div class="absolute flex items-center h-5">
                            <input
                              id="privacy_public"
                              name="privacy_public"
                              aria-describedby="privacy_public_description"
                              type="radio"
                              class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300"
                            />
                          </div>
                          <div class="pl-7 text-sm">
                            <label
                              for="privacy_public"
                              class="font-medium text-gray-900"
                            >
                              Public access
                            </label>
                            <p
                              id="privacy_public_description"
                              class="text-gray-500"
                            >
                              Everyone with the link will see this project.
                            </p>
                          </div>
                        </div>
                        <div>
                          <div class="relative flex items-start">
                            <div class="absolute flex items-center h-5">
                              <input
                                id="privacy_private-to-project"
                                name="privacy_private-to-project"
                                aria-describedby="privacy_private-to-project_description"
                                type="radio"
                                class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300"
                              />
                            </div>
                            <div class="pl-7 text-sm">
                              <label
                                for="privacy_private-to-project"
                                class="font-medium text-gray-900"
                              >
                                Private to project members
                              </label>
                              <p
                                id="privacy_private-to-project_description"
                                class="text-gray-500"
                              >
                                Only members of this project would be able to
                                access.
                              </p>
                            </div>
                          </div>
                        </div>
                        <div>
                          <div class="relative flex items-start">
                            <div class="absolute flex items-center h-5">
                              <input
                                id="privacy_private"
                                name="privacy_private"
                                aria-describedby="privacy_private-to-project_description"
                                type="radio"
                                class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300"
                              />
                            </div>
                            <div class="pl-7 text-sm">
                              <label
                                for="privacy_private"
                                class="font-medium text-gray-900"
                              >
                                Private to you
                              </label>
                              <p
                                id="privacy_private_description"
                                class="text-gray-500"
                              >
                                You are the only one able to access this
                                project.
                              </p>
                            </div>
                          </div>
                        </div>
                      </div>
                    </fieldset>
                  </div>
                  <div class="pt-4 pb-6">
                    <div class="flex text-sm">
                      <link-default icon="link">Copy Webhook URL</link-default>
                    </div>
                    <div class="mt-4 flex text-sm">
                      <a
                        href="#"
                        class="group inline-flex items-center text-gray-500 hover:text-gray-900"
                      >
                        <!-- Heroicon name: question-mark-circle -->
                        <svg
                          class="h-5 w-5 text-gray-400 group-hover:text-gray-500"
                          xmlns="http://www.w3.org/2000/svg"
                          viewBox="0 0 20 20"
                          fill="currentColor"
                          aria-hidden="true"
                        >
                          <path
                            fill-rule="evenodd"
                            d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z"
                            clip-rule="evenodd"
                          />
                        </svg>
                        <span class="ml-2"> Learn more about plans </span>
                      </a>
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
  props: ["showForm"],
  watch: {
    showForm(newVal, oldVal) {
      this.hidden = !newVal;

      delay(() => {
        this.show = newVal;
      }, 5);
    },
  },
  data() {
    return {
      show: this.showForm,
      hidden: true,
      form: this.$inertia.form({
        type: "file",
        cluster_id: 1,
        name: "",
        description: "",
      }),
    };
  },
  methods: {
    inertiaToVuelidate() {
      return {
        validations: {
          $anyError: form.errors.name,
          $pending: form.processing,
          $dirty: true,
        },
        errorMessages: {
          required: "JSON Service account key is required.",
          isValid: "The service account JSON isn't valid",
        },
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
