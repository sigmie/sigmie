<template>
  <app title="Tokens">

    <div class="flex md:items-center md:justify-between mb-4">
      <div class="flex-1 min-w-0">
        <h2
          class="text-lg font-bold leading-7 text-gray-900 sm:text-3xl sm:leading-9 sm:truncate"
        >API Tokens</h2>
      </div>
    </div>

    <div class="flex flex-col">
      <div class="-my-2 py-2 overflow-x-auto sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
        <div
          class="align-middle inline-block min-w-full shadow overflow-hidden rounded-lg border-b border-gray-200"
        >
          <table class="min-w-full max-w-full">
            <thead>
              <tr>
                <th
                  class="pl-4 md:pl-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider"
                >Name</th>
                <th
                  class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider"
                >Status</th>
                <th
                  class="px-2 md:px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider"
                >Token</th>
                <th class="px-6 py-3 border-b border-gray-200 bg-gray-50"></th>
              </tr>
            </thead>
            <tbody class="bg-white">
              <tr v-for="(token, index) in reactiveTokens" :key="index">
                <td class="pl-4 md:pl-6 py-4 whitespace-no-wrap border-b border-gray-200">
                  <div class="flex items-center">
                    <div class>
                      <div class="text-sm leading-5 font-medium text-gray-900">{{ token.name }}</div>
                      <div
                        class="text-sm leading-5 text-gray-500 hidden md:block"
                        v-if="token.name === 'Admin'"
                      >
                        This is the API key to use
                        <b>only</b> in your backend.
                      </div>
                      <div class="text-sm leading-5 text-gray-500 hidden md:block" v-else>
                        This is the public API key to use in
                        <br />your frontend code.
                      </div>
                    </div>
                  </div>
                </td>
                <td class="px-4 md:px-6 py-4 whitespace-no-wrap border-b border-gray-200">
                  <div
                    @click="()=> toogleActive(token.id, token.cluster_id, index)"
                    v-if="token.active"
                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 cursor-pointer w-16 text-center"
                  >
                    <span class="mx-auto">Active</span>
                  </div>
                  <div
                    @click="()=> toogleActive(token.id, token.cluster_id, index)"
                    v-else
                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 cursor-pointer w-16 text-center"
                  >
                    <span class="mx-auto">Inactive</span>
                  </div>
                </td>
                <td
                  v-if="token.value"
                  class="px-0 md:px-6 py-1 border-b border-gray-200 text-sm leading-5 text-gray-500 w-full"
                >
                  <textarea
                    @click="selectElement"
                    rows="1"
                    readonly
                    :value="token.value"
                    class="focus:outline-none inline-flex resize-none p-1 w-full"
                  />
                </td>
                <td
                  v-else
                  class="px-6 py-4 whitespace-no-wrap border-b border-gray-200 text-sm leading-5 text-gray-400 w-full"
                >
                  <div class="italic w-10 md:w-96">hidden</div>
                </td>
                <td
                  class="pr-2 md:px-6 py-4 whitespace-no-wrap text-right border-b border-gray-200 text-sm leading-5 font-medium w-0 md:w-60"
                >
                  <a
                    v-if="token.value"
                    v-clipboard="()=> token.value"
                    v-clipboard:success="() => onCopy(index)"
                    href="#"
                    class="text-orange-500 cursor-pointer hidden md:block w-full"
                  >{{ token.actionText }}</a>

                  <a
                    v-else
                    @click="() => regenerate(token.id, token.cluster_id, index)"
                    class="text-orange-500 cursor-pointer hidden md:block w-full"
                  >Regenerate</a>

                  <a
                    v-if="token.value"
                    v-clipboard="()=> token.value"
                    v-clipboard:success="() => onCopy(index)"
                    href="#"
                    class="text-orange-500 cursor-pointer"
                  >
                    <component
                      class="text-orange-500 cursor-pointer h-5 block md:hidden w-full"
                      :is="'icon-'+token.actionIcon"
                    ></component>
                  </a>
                  <a
                    v-else
                    @click="() => regenerate(token.id, token.cluster_id, index)"
                    class="text-orange-500 cursor-pointer"
                  >
                    <component
                      class="text-orange-500 cursor-pointer h-5 block md:hidden w-full"
                      :is="'icon-'+token.actionIcon"
                    ></component>
                  </a>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </app>
</template>

<script>
import App from "../layouts/app";
import delay from "lodash/delay";
import forEach from "lodash/forEach";

export default {
  components: {
    App
  },
  props: ["tokens"],
  data() {
    return {
      reactiveTokens: {}
    };
  },
  beforeMount() {
    forEach(this.tokens, (value, key) => {
      value.actionText = "Copy";
      value.actionIcon = value.value ? "duplicate" : "refresh";
      this.reactiveTokens[key] = value;
    });
  },
  methods: {
    updateAction(index, text, icon) {
      let tokens = this.reactiveTokens;
      tokens[index].actionText = text;
      tokens[index].actionIcon = icon;
      this.reactiveTokens = tokens;

      this.$forceUpdate();
    },
    selectElement(event) {
      event.target.select();
    },
    onCopy(index) {
      this.updateAction(index, "Copied!", "check-circle");

      delay(
        ([self, index]) => self.updateAction(index, "Copy", "duplicate"),
        300,
        [this, index]
      );
    },
    async toogleActive(tokenId, clusterId, index) {
      let response = await this.$http.put(
        this.$route("token.toogle", {
          clusterToken: tokenId,
          cluster: clusterId
        })
      );

      this.reactiveTokens[index].active = response.data === 1;

      this.$forceUpdate();
    },
    async regenerate(tokenId, clusterId, index) {
      let response = await this.$http.put(
        this.$route("token.regenerate", {
          clusterToken: tokenId,
          cluster: clusterId
        })
      );

      this.reactiveTokens[index].value = response.data.value;
      this.reactiveTokens[index].id = response.data.id;

      this.updateAction(index, "Regenerated!", "check-circle");

      delay(
        ([self, index]) => self.updateAction(index, "Copy", "duplicate"),
        300,
        [this, index]
      );
    }
  }
};
</script>

<style>
</style>
