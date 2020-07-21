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
          class="align-middle inline-block min-w-full shadow overflow-hidden sm:rounded-lg border-b border-gray-200"
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
                      <div class="text-sm leading-5 text-gray-500" v-if="token.name === 'Admin'">
                        This is the API key
                        <b>only</b> in your backend.
                      </div>
                      <div
                        class="text-sm leading-5 text-gray-500"
                        v-else
                      >This is the public API key to use in your frontend code.</div>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-no-wrap border-b border-gray-200">
                  <span
                    v-if="token.active"
                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800"
                  >Active</span>
                  <span
                    v-else
                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800"
                  >Inactive</span>
                </td>
                <td
                  v-if="token.value"
                  class="px-6 py-4 whitespace-no-wrap border-b border-gray-200 text-sm leading-5 text-gray-500 max-w-sm overflow-x-scroll"
                >
                  <input
                    type="text"
                    @click="selectElement"
                    readonly
                    :value="token.value"
                    class="focus:outline-none"
                  />
                </td>
                <td
                  v-else
                  class="px-6 py-4 whitespace-no-wrap border-b border-gray-200 text-sm leading-5 text-gray-500"
                >****************</td>
                <td
                  class="px-6 py-4 whitespace-no-wrap text-right border-b border-gray-200 text-sm leading-5 font-medium"
                >
                  <a
                    v-if="token.value"
                    v-clipboard="()=> token.value"
                    v-clipboard:success="() => onCopy(index)"
                    href="#"
                    class="text-indigo-600 hover:text-indigo-900 cursor-pointer"
                  >{{ token.actionText }}</a>
                  <a
                    v-else
                    @click="() => regenerate(token.id, token.cluster_id, index)"
                    class="text-indigo-600 hover:text-indigo-900 cursor-pointer"
                  >Regenerate</a>
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
      this.reactiveTokens[key] = value;
    });
  },
  methods: {
    updateActionText(index, text) {
      let tokens = this.reactiveTokens;
      tokens[index].actionText = text;
      this.reactiveTokens = tokens;

      this.$forceUpdate();
    },
    selectElement(event) {
      event.target.select();
    },
    onCopy(index) {
      this.updateActionText(index, "Copied!");

      delay(([self, index]) => self.updateActionText(index, "Copy"), 300, [
        this,
        index
      ]);
    },
    async regenerate(tokenId, clusterId, index) {
      let response = await this.$http.put(
        this.$route("token.update", {
          token: tokenId,
          cluster: clusterId
        })
      );

      this.reactiveTokens[index].value = response.data;

      this.updateActionText(index, "Regenerated!");

      delay(([self, index]) => self.updateActionText(index, "Copy"), 300, [
        this,
        index
      ]);
    }
  }
};
</script>

<style>
</style>
