<template>
  <div>
    <div class="flex flex-col-reverse lg:grid lg:grid-cols-3 gap-6">
      <div class="flex-1 md:col-span-2 sm:px-0">
        <div class="shadow sm:rounded-md sm:overflow-hidden">
          <div v-if="state === 'chosen'" class="px-4 py-5 bg-white sm:p-6 rounded-md">
            <div
              class="block hover:bg-gray-50 focus:outline-none focus:bg-gray-50 transition duration-150 ease-in-out"
            >
              <div class="flex items-center px-4 py-4 sm:px-6">
                <div class="min-w-0 flex-1 flex items-center">
                  <div class="flex-shrink-0 w-16">
                    <img class="h-10 w-auto mx-auto" :src="assetUrl+'/img/'+chosen.logo" alt />
                  </div>
                  <div class="min-w-0 flex-1 flex items-center pl-4">
                    <p>{{ chosen.name }}</p>
                  </div>
                </div>
                <div>
                  <button-secondary @click="state = 'choosing'" text="Change"></button-secondary>
                </div>
              </div>
            </div>
            <form-textarea
              :value="serviceAccount"
              @change="(value) => set('serviceAccount',value)"
              class="pt-4"
              id="serviceAccount"
              name="json"
              type="text"
              :validations="$v.serviceAccount"
              :error-messages="errorMessages.serviceAccount"
              label="Service account JSON"
            >
              <template v-slot:info>
                <p class="text-gray-500 text-sm mt-2">
                  More info at
                  <a
                    target="_blank"
                    class="text-orange-700"
                    href="https://docs.sigmie.com"
                  >https://docs.sigmie.com/app/google</a>
                </p>
              </template>
            </form-textarea>
          </div>
          <div v-if="state === 'choosing'" class="px-4 py-5 bg-white sm:p-6 rounded-md">
            <ul>
              <li v-for="(provider, key, index) in providers" :key="index">
                <div
                  class="block hover:bg-gray-50 focus:outline-none focus:bg-gray-50 transition duration-150 ease-in-out"
                >
                  <div class="flex items-center px-4 py-4 sm:px-6">
                    <div class="min-w-0 flex-1 flex items-center">
                      <div class="flex-shrink-0 w-16">
                        <img class="h-10 w-auto mx-auto" :src="assetUrl+'/img/'+provider.logo" alt />
                      </div>
                      <div class="min-w-0 flex-1 flex items-center pl-4">
                        <p>{{ provider.name }}</p>
                      </div>
                    </div>
                    <div>
                      <button-secondary
                        v-if="provider.active"
                        @click="choose(provider)"
                        text="Choose"
                      ></button-secondary>
                      <button-disabled v-else text="Choose"></button-disabled>
                    </div>
                  </div>
                </div>
              </li>
            </ul>
          </div>
        </div>
      </div>
      <div class="flex-1 md:col-span-1 pt-4 sm:pt-0">
        <div class="pb-2 md:pb-4 lg:pb-0 lg:px-4">
          <h3 class="text-lg font-medium leading-6 text-gray-900">Cloud provider</h3>
          <p class="mt-1 text-sm leading-5 text-gray-600">
            Your Cloud provider for your Elasticsearch cluster.
            <br />
            <br />AWS and DigitalOcean aren't available yet.
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { required, helpers } from "vuelidate/lib/validators";
import { forEach } from "lodash";

const assetUrl = process.env.MIX_ASSET_URL;

const mustBeJSON = value => {
  try {
    JSON.parse(value);
  } catch (e) {
    return false;
  }
  return true;
};

export default {
  validations: {
    serviceAccount: {
      mustBeJSON,
      required
    }
  },
  data() {
    return {
      errorMessages: {
        serviceAccount: {
          required: "JSON Service account key is required.",
          mustBeJSON: "Not a valid json"
        }
      },
      providers: {
        google: {
          id: "google",
          name: "Google Cloud Platform",
          logo: "gcloud_logo.png",
          active: true
        },
        aws: {
          id: "aws",
          name: "Amazon Web Services",
          logo: "aws_logo.png",
          active: false
        },
        digitalocean: {
          id: "digitalocean",
          name: "DigitalOcean",
          logo: "do_logo.png",
          active: false
        }
      },
      assetUrl: assetUrl,
      chosen: "google",
      state: "choosing",
      serviceAccount: ""
    };
  },
  watch: {
    serviceAccount(newValue, oldValue) {
      let provider = {
        id: this.providers.google.id,
        creds: newValue,
        name: this.providers.google.name
      };

      this.$emit("providerChange", provider);
    },
    "$v.$invalid": function(value) {
      this.$emit("validate", value);
    }
  },
  methods: {
    choose(provider) {
      this.chosen = provider;
      this.state = "chosen";
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
