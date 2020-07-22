<template>
  <app>
    <div class="flex md:items-center md:justify-between md:mb-4">
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
          <table class="min-w-full">
            <thead>
              <tr>
                <th
                  class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider"
                >Name</th>
                <th
                  class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider"
                >Status</th>
                <th
                  class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider"
                >Token</th>
                <th class="px-6 py-3 border-b border-gray-200 bg-gray-50"></th>
              </tr>
            </thead>
            <tbody class="bg-white">
              <tr v-for="(token, index) in reactiveTokens" :key="index">
                <td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200">
                  <div class="flex items-center">
                    <div class>
                      <div class="text-sm leading-5 font-medium text-gray-900">{{ token.name }}</div>
                      <div
                        class="text-sm leading-5 text-gray-500 hidden md:block"
                        v-if="token.name === 'Admin'"
                      >
                        This is the API key
                        <b>only</b> in your backend.
                      </div>
                      <div class="text-sm leading-5 text-gray-500 hidden md:block" v-else>
                        This is the public API key to use in
                        <br />your frontend code.
                      </div>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200">
                  <span
                    @click="()=> toogleActive(token.id, token.cluster_id, index)"
                    v-if="token.active"
                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 cursor-pointer"
                  >Active</span>
                  <span
                    @click="()=> toogleActive(token.id, token.cluster_id, index)"
                    v-else
                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 cursor-pointer"
                  >Inactive</span>
                </td>
                <td
                  v-if="token.value"
                  class="px-6 py-4 whitespace-no-wrap border-b border-gray-200 text-sm leading-5 text-gray-500 overflow-x-scroll"
                >
                  <textarea
                    @click="selectElement"
                    rows="5"
                    col="20"
                    readonly
                    :value="token.value"
                    class="focus:outline-none resize-none p-1"
                  />
                </td>
                <td
                  v-else
                  class="px-6 py-4 whitespace-no-wrap border-b border-gray-200 text-sm leading-5 text-gray-400 italic"
                >hidden</td>
                <td
                  class="px-6 py-4 whitespace-no-wrap text-right border-b border-gray-200 text-sm leading-5 font-medium w-8"
                >
                  <a
                    v-if="token.value"
                    v-clipboard="()=> token.value"
                    v-clipboard:success="() => onCopy(index)"
                    href="#"
                    class="text-orange-500 cursor-pointer hidden md:block"
                  >{{ token.actionText }}</a>

                  <a
                    v-else
                    @click="() => regenerate(token.id, token.cluster_id, index)"
                    class="text-orange-500 cursor-pointer hidden md:block"
                  >Regenerate</a>

                  <a
                    v-if="token.value"
                    v-clipboard="()=> token.value"
                    v-clipboard:success="() => onCopy(index)"
                    href="#"
                    class="text-orange-500 cursor-pointer"
                  >
                    <component
                      class="text-orange-500 cursor-pointer h-5 block md:hidden"
                      :is="'icon-'+token.actionIcon"
                    ></component>
                  </a>
                  <a
                    v-else
                    @click="() => regenerate(token.id, token.cluster_id, index)"
                    class="text-orange-500 cursor-pointer"
                  >
                    <component
                      class="text-orange-500 cursor-pointer h-5 block md:hidden"
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
  mounted() {
    forEach(this.tokens, (value, key) => {
      value.actionText = "Copy";
      value.actionIcon = "duplicate";
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
          token: tokenId,
          cluster: clusterId
        })
      );

      this.reactiveTokens[index].active = (response.data === 1);

      this.$forceUpdate();
    },
    async regenerate(tokenId, clusterId, index) {
      let response = await this.$http.put(
        this.$route("token.regenerate", {
          token: tokenId,
          cluster: clusterId
        })
      );

      this.reactiveTokens[index].value = response.data;

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
