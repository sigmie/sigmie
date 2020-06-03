<template>
  <app>
    <modal
      title="Create Elasticsearch cluster ?"
      primaryText="Confirm"
      secondaryText="Cancel"
      @primaryAction="submit"
      @secondaryAction="showConfirmation = false"
      @clickAway="showConfirmation = false"
      @onEsc="showConfirmation = false"
      :icon="false"
      :show="showConfirmation"
      type="info"
    >
      <div class="px-4 py-5 sm:px-6">
        <dl class="grid grid-cols-1 col-gap-4 row-gap-8 sm:grid-cols-2">
          <div class="sm:col-span-1">
            <dt class="text-sm leading-5 font-medium text-gray-500">Name</dt>
            <dd class="mt-1 text-sm leading-5 text-gray-900">{{ name }}</dd>
          </div>
          <div class="sm:col-span-1" v-if="provider">
            <dt class="text-sm leading-5 font-medium text-gray-500">Provider</dt>
            <dd class="mt-1 text-sm leading-5 text-gray-900">{{ provider.name }}</dd>
          </div>
          <div class="sm:col-span-1" v-if="dataCenter">
            <dt class="text-sm leading-5 font-medium text-gray-500">Data center</dt>
            <dd class="mt-1 text-sm leading-5 text-gray-900">{{ dataCenter.name }}</dd>
          </div>
          <div class="sm:col-span-1">
            <dt class="text-sm leading-5 font-medium text-gray-500">Number of Nodes</dt>
            <dd class="mt-1 text-sm leading-5 text-gray-900">{{ nodes }}</dd>
          </div>
          <div class="sm:col-span-2">
            <dd class="text-sm leading-5 font-medium text-gray-500">
              Your cluster will become available at:
              <br />
              <a class="text-orange-400" target="_blank" href>https://{{name}}.search.sigmie.com</a>
            </dd>
          </div>
        </dl>
      </div>
    </modal>

    <div class="max-w-4xl mx-auto">
      <div class="md:flex md:items-center md:justify-between mb-4">
        <div class="flex-1 min-w-0">
          <h2
            class="text-lg font-bold leading-7 text-gray-900 sm:text-3xl sm:leading-9 sm:truncate"
          >Add new cluster</h2>
        </div>
        <div class="mt-4 flex md:mt-0 md:ml-4">
          <span>
            <a class="tracking-wide text-sm text-gray-400" href>Use existing</a>
          </span>
        </div>
      </div>

      <general
        @nameChange="(value)=> set('name', value)"
        @validate="(invalid)=> this.sections.general.invalid = invalid"
      ></general>

      <separator></separator>

      <provider
        @providerChange="(value)=> set('provider', value)"
        @validate="(invalid)=> this.sections.provider.invalid = invalid"
      ></provider>

      <separator></separator>

      <search
        @nodesChange="(value)=> set('nodes', parseFloat(value))"
        @dataCenterChange="(value)=> set('dataCenter', value)"
        @usernameChange="(value)=> set('username', value)"
        @passwordChange="(value)=> set('password', value)"
        @submit="showConfirmation = true"
        :disabled="sections.search.invalid || sections.general.invalid || sections.provider.invalid"
        @validate="(invalid)=> this.sections.search.invalid = invalid"
      ></search>
    </div>
  </app>
</template>

<script>
import App from "../layouts/app";

export default {
  components: {
    App,
    general: require("./_partials/create/general").default,
    provider: require("./_partials/create/provider").default,
    separator: require("./_partials/create/separator").default,
    search: require("./_partials/create/search").default
  },
  data() {
    return {
      sections: {
        general: {
          invalid: true
        },
        search: {
          invalid: true
        },
        provider: {
          invalid: true
        }
      },
      showConfirmation: false,
      name: "",
      provider: null,
      dataCenter: null,
      username: "",
      password: "",
      nodes: null
    };
  },
  methods: {
    submit() {
      let cluster = {
        nodes: this.nodes,
        username: this.username,
        password: this.password,
        dataCenter: this.dataCenter.id,
        provider: this.provider.id,
        creds: this.provider.creds,
        name: this.name
      };

      this.$inertia.post("/cluster", cluster);
    },
    set(key, value) {
      this[key] = value;
    }
  }
};
</script>

<style>
</style>
