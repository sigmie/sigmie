<template>
  <div class="shadow sm:rounded-md sm:overflow-hidden">
    <div v-if="state === 'chosen'" class="px-4 py-5 bg-white sm:p-6 rounded-t-md">
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
        :validations="$v.serviceAccount"
        :error-messages="errorMessages.serviceAccount"
        label="Service account JSON"
      >
        <template v-slot:info>
          <p class="text-gray-500 text-sm mt-2">
            More info at
            <a
              target="_blank"
              class="text-theme-orange-light-900"
              href="https://docs.sigmie.com"
            >https://docs.sigmie.com/app/google</a>
          </p>
        </template>
      </form-textarea>
    </div>
    <div v-if="state === 'choosing'" class="px-4 py-5 bg-white sm:p-6 rounded-t-md">
      <div class="pb-2">
        <h3 class="text-lg leading-6 font-medium text-gray-900">Cloud provider</h3>
        <p class="mt-1 text-sm leading-5 text-gray-500">Chose your desired cloud platform.</p>
      </div>
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
                <button-secondary v-if="provider.active" @click="choose(provider)" text="Choose"></button-secondary>
                <button-disabled v-else text="Choose"></button-disabled>
              </div>
            </div>
          </div>
        </li>
      </ul>
    </div>

    <div class="pt-5 pb-3">
      <div class="flex justify-end">
        <span v-if="$page.props.projects.length > 0" class="mr-6 inline-flex rounded-md">
          <a class="px-4 py-2 tracking-wide text-sm text-gray-400 float-right" href>Cancel</a>
        </span>
        <span class="inline-flex rounded-md shadow-sm mr-3">
          <button-primary @click="$emit('submit')" :disabled="!isProjectValid || $v.$invalid" text="Create"></button-primary>
        </span>
      </div>
    </div>
  </div>
</template>

<script>
import { required, helpers } from "vuelidate/lib/validators";
import { forEach } from "lodash";

const assetUrl = process.env.MIX_ASSET_URL;

export default {
  props: ['isProjectValid'],
  validations: {
    serviceAccount: {
      required,
      isValid(value) {
        if (value === "") {
          return true;
        }

        try {
          JSON.parse(value);
        } catch (e) {
          return false;
        }

        value = JSON.parse(value);

        let route = this.$route("project.validate.provider");

        return this.$http
          .post(route, { id: "google", creds: JSON.stringify(value) })
          .then(response => response.data.valid);
      }
    }
  },
  data() {
    return {
      errorMessages: {
        serviceAccount: {
          required: "JSON Service account key is required.",
          isValid: "The service account JSON isn't valid"
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
