<template>
  <app>
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
                  <p class="text-gray-500 text-sm mt-2">Optionaly write some notes about the project</p>
                </template>
              </form-textarea>
            </div>
          </div>
        </div>
        <div class="pt-5 pb-3">
          <div class="flex justify-end">
            <span v-if="hasProjects" class="mr-6 inline-flex rounded-md">
              <a class="tracking-wide text-sm text-gray-400 float-right" href>Cancel</a>
            </span>
            <span class="mr-6 inline-flex rounded-md shadow-sm">
              <button-primary @click="$emit('submit')" text="Create"></button-primary>
            </span>
          </div>
        </div>
        <div class="flex-1 md:col-span-1 pt-4 sm:pt-0"></div>
      </div>
    </div>
  </app>
</template>

<script>
import App from "../layouts/app";

import { required, alphaNum } from "vuelidate/lib/validators";

export default {
  props: ["hasProjects"],
  validations: {
    name: {
      required,
      alphaNum
    },
    description: {}
  },
  components: {
    App
  },
  data() {
    return {
      name: "",
      description: "",
      errorMessages: {
        name: {
          required: "Name is required.",
          alphaNum: "Name can contain only letters and numbers."
        },
        description: {}
      }
    };
  },
  methods: {
    set(key, value) {
      this[key] = value;
      this.$v[key].$touch();
    }
  }
};
</script>

<style>
</style>
