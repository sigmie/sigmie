<template>
  <div>
    <div class="flex flex-col-reverse lg:grid lg:grid-cols-3 gap-6">
      <div class="flex-1 md:col-span-2 sm:px-0 sm:pt-2">
        <div class="shadow rounded-md sm:overflow-hidden">
          <div class="px-4 py-5 bg-white sm:p-6 rounded-md">
            <div class="grid grid-cols-3 gap-6">
              <div class="col-span-3 lg:col-span-2">
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
      <div class="flex-1 md:col-span-1 pt-4 sm:pt-0">
        <div class="pb-2 md:pb-4 lg:pb-0 lg:px-4">
          <h3 class="text-lg font-medium md:leading-6 text-gray-900">General</h3>
          <p
            v-if="name !== '' && $v.name.invalid === false"
            class="mt-1 text-sm leading-5 text-gray-600"
          >
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

        let route = this.$route("cluster.validate.name", [value]);

        return this.$http.get(route).then(response => response.data.valid);
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
  watch: {
    "$v.$invalid": function(value) {
      this.$emit("validate", value);
    }
  },
  methods: {
    set(key, value) {
      this[key] = value;
      this.$v[key].$touch();

      this.$emit(`${key}Change`, value);
    }
  }
};
</script>

<style>
</style>
