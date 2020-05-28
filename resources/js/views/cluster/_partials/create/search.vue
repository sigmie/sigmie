<template>
  <div>
    <div class="md:grid md:grid-cols-3 md:gap-6">
      <div class="md:mt-0 md:col-span-2">
        <div class="shadow sm:rounded-md sm:overflow-hidden">
          <div class="px-4 py-5 bg-white sm:p-6">
            <div class="grid grid-cols-3 gap-6">
              <div class="col-span-3 sm:col-span-3">
                <legend class="text-base leading-6 font-medium text-gray-900">Cluster information</legend>
                <p
                  class="text-sm leading-5 text-gray-500"
                >Choose the main Region and the desired amount of nodes for your cluster.</p>
              </div>
              <div class="col-span-2 sm:col-span-2">
                <form-select
                  label="Datacenter location"
                  name="data_center"
                  id="data-center"
                  v-model.trim="$v.dataCenter.$model"
                  aria-label="Data center"
                  :items="['Asia Pacific', 'Europe', 'North America', 'South America']"
                  :validations="$v.dataCenter"
                ></form-select>
              </div>
              <div class="col-span-1 sm:col-span-1">
                <form-input
                  label="Nodes"
                  name="nodes"
                  type="number"
                  id="nodes"
                  v-model.trim="$v.nodes.$model"
                  aria-label="Nodes count"
                  :validations="$v.nodes"
                  :error-messages="errorMessages.nodes"
                ></form-input>
              </div>
            </div>
            <div class="grid grid-cols-4 gap-6 mt-4">
              <div class="col-span-4 sm:col-span-4">
                <legend class="text-base leading-6 font-medium text-gray-900">Security</legend>
                <p class="text-sm leading-5 text-gray-500">
                  Specify the
                  <a
                    target="_blank"
                    href="https://en.wikipedia.org/wiki/Basic_access_authentication"
                  >Basic authentication</a> credentials for direct access to your Elasticsearch.
                </p>
              </div>
              <div class="col-span-2 sm:col-span-2">
                <form-input
                  :value="username"
                  label="Username"
                  @change="(value) => set('username',value)"
                  class="max-w-sm"
                  id="username"
                  name="username"
                  :validations="$v.username"
                  :error-messages="errorMessages.username"
                ></form-input>
              </div>

              <div class="col-span-2 sm:col-span-2">
                <form-input
                  :value="password"
                  label="Password"
                  @change="(value) => set('password',value)"
                  class="max-w-sm"
                  id="password"
                  name="password"
                  :validations="$v.password"
                  :error-messages="errorMessages.password"
                ></form-input>
              </div>
            </div>
          </div>
          <div class="px-4 py-3 bg-gray-50 h-auto">
            <div class="w-40 float-right">
              <button-primary text="Create cluster"></button-primary>
            </div>
          </div>
        </div>
      </div>
      <div class="md:col-span-1">
        <div class="px-4 sm:px-0">
          <h3 class="text-lg font-medium leading-6 text-gray-900">Search details</h3>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { required, minValue, maxValue } from "vuelidate/lib/validators";

export default {
  validations: {
    dataCenter: {
      required
    },
    nodes: {
      required,
      minValue: minValue(1),
      maxValue: maxValue(3)
    }
  },
  data() {
    return {
      dataCenter: "",
      nodes: 3,
      username: "",
      password: "",
      errorMessages: {
        dataCenter: {
          required: "Please choose a data center"
        },
        username: {
          required: "Basic auth username is required"
        },
        password: {
          required: "Basic auth password is required"
        },
        nodes: {
          required: "Please specify a the desired nodes count",
          minValue: "You need at least one node",
          maxValue: "The max amount of node is 3"
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
