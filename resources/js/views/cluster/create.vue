<template>
  <app title="Create Cluster">
    <modal
      title="Elasticsearch Cluster"
      primaryText="Create"
      secondaryText="Cancel"
      @primaryAction="submit"
      @secondaryAction="showConfirmation = false"
      @clickAway="showConfirmation = false"
      @onEsc="showConfirmation = false"
      :icon="false"
      :show="showConfirmation"
      type="info"
    >
      <div class="sm:p-0 px-2 py-1">
        <p class>
          We will create and protect your Elasticsearch cluster with basic auth and
          a free SSL Certificate provided by Cloudflare.
          <br />
          <br />Your cluster will become available at:
          <a
            class="text-orange-400"
            target="_blank"
            :href="'https://'+name+'.sigmie.app'"
          >https://{{name}}.sigmie.app</a>
        </p>
        <div class="py-5">
          <dl class="grid col-gap-4 row-gap-8 grid-cols-3">
            <div class="col-span-1">
              <dt class="text-sm leading-5 font-medium text-gray-500">Name</dt>
              <dd class="mt-1 text-sm leading-5 text-gray-900">{{ name }}</dd>
            </div>
              <div class="col-span-1" v-if="dataCenter">
              <dt class="text-sm leading-5 font-medium text-gray-500">Data center</dt>
              <dd class="mt-1 text-sm leading-5 text-gray-900">{{ dataCenter.name }}</dd>
            </div>
            <div class="col-span-1">
              <dt class="text-sm leading-5 font-medium text-gray-500">Number of Nodes</dt>
              <dd class="mt-1 text-sm leading-5 text-gray-900">{{ nodes }}</dd>
            </div>

            <div class="col-span-3">
              <dd
                class="text-sm leading-5 font-normal tracking-normal text-gray-500 bg-gray-100 px-3 py-2 rounded-md"
              >
                <div class="flex align-middle">
                  <icon-info class="flex-none h-8 w-8 text-gray-400 mt-1 mr-3"></icon-info>
                  <div class="flex-1 text-sm">
                    You can access your Cluster either by using our API
                    with an
                    <a
                      class="text-orange-400"
                      target="_blank"
                      :href="$route('access-token.index')"
                    >Access Token</a> or directly by using your basic auth credentials.
                  </div>
                </div>
              </dd>
            </div>
          </dl>
        </div>
      </div>
    </modal>

    <div class="max-w-4xl mx-auto">
      <div class="flex md:items-center md:justify-between md:mb-4">
        <div class="flex-1 min-w-0">
          <h2
            class="text-lg font-bold leading-7 text-gray-900 sm:text-3xl sm:leading-9 sm:truncate"
          >Add new cluster</h2>
        </div>
        <div class="flex-1 md:mt-0 md:ml-4">
          <a
            class="tracking-wide text-sm text-gray-400 float-right cursor-not-allowed"
            href="#"
          >Use existing</a>
        </div>
      </div>
      <general
        @nameChange="(value)=> set('name', value)"
        @validate="(invalid)=> this.sections.general.invalid = invalid"
      ></general>

      <separator></separator>

      <search
        @nodesChange="(value)=> set('nodes', parseFloat(value))"
        @dataCenterChange="(value)=> set('dataCenter', value)"
        @usernameChange="(value)=> set('username', value)"
        @passwordChange="(value)=> set('password', value)"
        @submit="showConfirmation = true"
        :disabled="sections.search.invalid || sections.general.invalid"
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
        }
      },
      showConfirmation: false,
      name: "",
      dataCenter: null,
      username: "",
      password: "",
      nodes: null
    };
  },
  methods: {
    submit() {
      let cluster = {
        nodes_count: this.nodes,
        username: this.username,
        password: this.password,
        data_center: this.dataCenter.id,
        name: this.name,
        project_id: this.$page.project_id
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
