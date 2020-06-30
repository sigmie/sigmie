<template>
  <div>
    <div class="flex flex-col-reverse lg:grid lg:grid-cols-3 gap-6">
      <div class="flex-1 md:col-span-2 sm:px-0">
        <div class="shadow sm:rounded-md sm:overflow-hidden">
          <div class="px-4 py-5 bg-white sm:p-6 rounded-md">
            <div class="grid grid-cols-3 gap-6">
              <div class="col-span-4 sm:col-span-4">
                <legend class="text-base leading-6 font-medium text-gray-900">Cluster information</legend>
                <p
                  class="text-sm leading-5 text-gray-500"
                >Choose the main Region and the desired amount of nodes for your cluster.</p>
              </div>

              <div class="col-span-2 sm:col-span-2">
                <form-select
                  label="Datacenter location"
                  name="data_center"
                  @change="(value) => set('dataCenter',value)"
                  id="data-center"
                  v-model.trim="$v.dataCenter.$model"
                  aria-label="Data center"
                  :items="[ {id:'asia',name:'Asia Pacific'}, {id:'europe',name:'Europe'}, {id:'america',name: 'America'}]"
                  :validations="$v.dataCenter"
                ></form-select>
              </div>
              <div class="col-span-2 sm:col-span-2">
                <form-slider
                  :min="1"
                  :max="3"
                  :value="nodes"
                  @change="(value)=>set('nodes',value)"
                  label="Nodes"
                ></form-slider>
              </div>

              <div class="col-span-3" v-if="nodes === 1">
                <div class="rounded-md bg-yellow-50 p-2">
                  <div class="flex">
                    <div class="ml-3 flex-1 md:flex md:justify-between">
                      <p
                        class="text-sm leading-5 text-yellow-500"
                      >You won't have a healthy cluster by having only one node.</p>
                      <p class="mt-3 text-sm leading-5 md:mt-0 md:ml-6">
                        <a
                          href="https://docs.sigmie.com/knowledge/cluster-health"
                          target="_blank"
                          class="whitespace-no-wrap font-medium text-yellow-500 hover:text-yellow-400 transition ease-in-out duration-150"
                        >Details &rarr;</a>
                      </p>
                    </div>
                  </div>
                </div>
              </div>

            </div>
            <div class="grid grid-cols-4 gap-6 mt-4">
              <div class="col-span-4 sm:col-span-4">
                <legend class="text-base leading-6 font-medium text-gray-900">Security</legend>
                <p class="text-sm leading-5 text-gray-500">
                  Specify the
                  <a
                    class="hover:text-gray-600"
                    target="_blank"
                    href="https://en.wikipedia.org/wiki/Basic_access_authentication"
                  >basic authentication</a> credentials for direct access to your Elasticsearch.
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
                  type="password"
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
          <div class="pt-5 pb-3">
            <div class="flex justify-end">
              <span class="mr-6 inline-flex rounded-md shadow-sm">
                <button-primary :disabled="disabled" @click="$emit('submit')" text="Submit"></button-primary>
              </span>
            </div>
          </div>
        </div>
      </div>
      <div class="flex-1 md:col-span-1 pt-4 sm:pt-0">
        <div class="pb-2 md:pb-4 lg:pb-0 lg:px-4">
          <h3 class="text-lg font-medium leading-6 text-gray-900">Search details</h3>
          <p class="mt-1 text-sm leading-5 text-gray-600">
            Choose where you want your data to be stored and
            the desired number of Elasticsearch instances.
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { required, minValue, maxValue } from "vuelidate/lib/validators";

export default {
  props: ["disabled"],
  validations: {
    dataCenter: {
      required
    },
    username: {
      required
    },
    password: {
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
          minValue: "Min is 0",
          maxValue: "Max is 3"
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

      this.$emit(`${key}Change`, this[key]);
    }
  }
};
</script>

<style>
</style>
