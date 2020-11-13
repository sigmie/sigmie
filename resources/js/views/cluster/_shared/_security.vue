<template>
  <div>
    <div class="flex flex-col-reverse lg:grid lg:grid-cols-3 gap-6">
      <div class="flex-1 md:col-span-2 sm:px-0 shadow rounded-md">
        <div class="">
          <div class="px-4 py-5 bg-white sm:p-6 rounded-t-md">
            <div class="grid grid-cols-4 gap-6">
              <div class="col-span-4 sm:col-span-4">
                <legend class="text-base leading-6 font-medium text-gray-900">
                  Security
                </legend>
                <p class="text-sm leading-5 text-gray-500">
                  Specify the
                  <a
                    class="hover:text-gray-600"
                    target="_blank"
                    href="https://en.wikipedia.org/wiki/Basic_access_authentication"
                    >Basic Auth</a
                  >
                  credentials for direct access to your Elasticsearch.
                </p>
              </div>

              <div class="col-span-2 sm:col-span-2">
                <form-input
                  :value="username"
                  label="Username"
                  @change="(value) => set('username', value)"
                  class="max-w-sm"
                  id="username"
                  data-lpignore="true"
                  :lpignore="true"
                  name="username"
                  :validations="$v.username"
                  :error-messages="errorMessages.username"
                ></form-input>
              </div>
              <div class="col-span-2 sm:col-span-2">
                <form-input
                  type="password"
                  data-lpignore="true"
                  :value="password"
                  :lpignore="true"
                  label="Password"
                  @change="(value) => set('password', value)"
                  class="max-w-sm"
                  id="password"
                  name="password"
                  :validations="$v.password"
                  :error-messages="errorMessages.password"
                ></form-input>
              </div>
            </div>
          </div>
        </div>

        <div class="float-right py-3">
          <span class="mr-6 inline-flex">
            <button-primary
              :disabled="submitDisabled"
              @click.prevent="$emit('submit')"
              text="Submit"
            ></button-primary>
          </span>
        </div>
      </div>
      <div class="flex-1 md:col-span-1 pt-4 sm:pt-0">
        <div class="pb-2 md:pb-4 lg:pb-0 lg:px-4">
          <h3 class="text-lg font-medium leading-6 text-gray-900">
            Security details
          </h3>
          <p class="mt-1 text-sm leading-5 text-gray-600">
            We will hide your Cluster's public IP address behind Cloudflare and
            set up a Basic Authentication.
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import {
  required,
  minValue,
  maxValue,
  maxLength,
  minLength,
  alphaNum,
} from "vuelidate/lib/validators";

const cantContainColon = (value) => value.includes(":") === false;

export default {
  props: ["submitDisabled"],
  validations: {
    username: {
      alphaNum,
      required,
      cantContainColon,
      maxLength: maxLength(15),
      minLength: minLength(4),
    },
    password: {
      required,
      maxLength: maxLength(100),
      minLength: minLength(4),
    },
  },
  methods: {
    set(key, value) {
      this[key] = value;
      this.$v[key].$touch();
      this.$emit(key + "Change", value);
    },
  },
  watch: {
    "$v.$invalid": function (value) {
      this.$emit("validate", value);
    },
  },
  data() {
    return {
      username: "",
      password: "",
      errorMessages: {
        username: {
          alphaNum: "Username can contain only alphanumeric characters",
          maxLength: "Max username length is 15 chars",
          minLength: "Min username length is 4 chars",
          required: "Basic auth username is required",
          cantContainColon: "Username can't contain colon",
        },
        password: {
          required: "Basic auth password is required",
          maxLength: "Max password length is 100 chars",
          minLength: "Min password length is 4 chars",
        },
      },
    };
  },
};
</script>

<style scoped>
</style>
