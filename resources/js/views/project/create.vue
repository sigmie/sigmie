<template>
  <app title="Create Project">
    <div class="max-w-lg mx-auto">
      <div class="shadow rounded-md sm:overflow-hidden">
        <div class="px-4 py-5 bg-white sm:p-6 rounded-md">
          <div class="pb-2">
            <h3 class="text-lg leading-6 font-medium text-gray-900">New project</h3>
            <p
              class="mt-1 text-sm leading-5 text-gray-500"
            >Project is a space where your clusters will belong.</p>
          </div>
          <div class="grid grid-cols-3 gap-6">
            <div class="col-span-3 lg:col-span-2">
              <form-input
                :value="name"
                label="Name"
                @change="(value) => set('name',value)"
                id="name"
                name="name"
                :validations="$v.name"
                :error-messages="errorMessages.name"
              ></form-input>
            </div>

            <div class="col-span-3 lg:col-span-2">
              <form-textarea
                :value="description"
                @change="(value) => set('description',value)"
                id="description"
                name="description"
                type="text"
                :validations="$v.description"
                :error-messages="errorMessages.description"
                label="Description"
              >
                <template v-slot:info>
                  <p
                    class="text-gray-500 text-sm mt-2"
                  >Optionaly write some notes about the project.</p>
                </template>
              </form-textarea>
            </div>
          </div>
        </div>
      </div>

      <div class="pt-3">
        <provider
          @submit="submit"
          @providerChange="(value)=> provider.data = value"
          @validate="(invalid)=> this.provider.invalid = invalid"
        ></provider>
      </div>
    </div>
  </app>
</template>

<script>
import App from "../layouts/app";

import { required, helpers } from "vuelidate/lib/validators";
const alphaNum = helpers.regex("alpha", /^[a-zA-Z0-9-_]*$/i);

export default {
  validations: {
    name: {
      required,
      alphaNum
    },
    description: {}
  },
  components: {
    App,
    provider: require("./_partials/create/provider").default
  },
  data() {
    return {
      name: "",
      description: "",
      provider: {
        data: "",
        invalid: true
      },
      errorMessages: {
        name: {
          required: "Name is required.",
          alphaNum: "Name can contain only letters and numbers and dashes."
        },
        description: {}
      }
    };
  },
  methods: {
    submit() {
      this.$inertia.post(this.$route("project.store"), {
        name: this.name,
        description: this.description,
        provider: this.provider.data
      });
    },
    set(key, value) {
      this[key] = value;
      this.$v[key].$touch();
    }
  }
};
</script>

<style>
</style>
