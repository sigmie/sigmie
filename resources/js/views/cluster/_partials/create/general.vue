<template>
  <div>
    <div class="md:grid md:grid-cols-3 md:gap-6">
      <div class="md:mt-0 md:col-span-2">
        <div class="shadow sm:rounded-md sm:overflow-hidden">
          <div class="px-4 py-5 bg-white sm:p-6">
            <div class="grid grid-cols-3 gap-6">
              <div class="col-span-2 sm:col-span-2">
                <form-input
                  :value="name"
                  label="Name"
                  @change="(value) => set('name',value)"
                  class="max-w-sm"
                  id="name"
                  name="name"
                  :validations="$v.name"
                  suffix="search.sigmie.com"
                  :error-messages="errorMessages.name"
                ></form-input>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="md:col-span-1">
        <div class="px-4 sm:px-0">
          <h3 class="text-lg font-medium leading-6 text-gray-900">General</h3>
          <p v-if="name !== ''" class="mt-1 text-sm leading-5 text-gray-600">
            Basic information about your search.
            <br />
            <br />You search will be available at
            <br />
            <a
              class="text-orange-700"
              :href="'https://'+name+'.search.sigmie.com'"
              target="_blank"
            >https://{{ name }}.search.sigmie.com</a>.
          </p>
          <p
            v-else
            class="mt-1 text-sm leading-5 text-gray-600"
          >General information about your search.</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { required, helpers } from "vuelidate/lib/validators";

// Allow only letters and dashes
const alphaDashes = helpers.regex("alpha", /^[a-z-]*$/);

export default {
  validations: {
    name: {
      required,
      alphaDashes,
      isUnique(value) {
        if (value === "") {
          return true;
        }

        return this.$http.get(`/cluster/name/${value}`, {
          validateStatus: function(status) {
            return status === 404; // Resolve only if the status code is less than 500
          }
        });
      }
    }
  },
  data() {
    return {
      name: "",
      errorMessages: {
        name: {
          required: "Name field is required.",
          alphaDashes: "Name must be lowercase letters, numbers, and hyphens.",
          isUnique: "A search with this name already exists."
        }
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
