<template>
  <app>
    <div class="grid grid-flow-col grid-cols-2 gap-x-4">
      <div class="col-span-2">
        <div class="grid grid-flow-row grid-cols-1 gap-y-4">
          <div class="col-span-1">
            <div class="bg-white shadow rounded-lg">
              <div class="p-4">
                <form-input
                  v-model="queryText"
                  type="text"
                  label="Query"
                  id="query-text"
                  name="query-text"
                ></form-input>

                <form-select
                  label="Indices"
                  name="data_center"
                  id="data-center"
                  aria-label="Data center"
                  displayKey="name"
                  v-model="index"
                  :items="indicesNames"
                ></form-select>

                <form-multiselect
                  label="Multiselect"
                  name="nameee"
                  id="idddd"
                  aria-label="Data center"
                  displayKey="name"
                  :items="indicesNames"
                ></form-multiselect>
              </div>
            </div>
          </div>

          <highlight v-if="results" :results="results.hits.hits"> </highlight>

          <results v-if="results" :results="results.hits.hits"> </results>

          <div class="col-span-1">
            <div class="grid grid-cols-2 gap-x-2 grid-flow-row">
              <div class="col-span-1">
                <div class="bg-white overflow-hidden shadow rounded-lg">
                  <div class="p-4">
                    <form-textarea
                      rows="10"
                      v-model="query"
                      id="description"
                      name="description"
                      label="Query"
                    ></form-textarea>
                  </div>
                </div>
              </div>
              <div class="col-span-1">
                <div class="bg-white overflow-hidden shadow rounded-lg">
                  <div class="p-4">
                    <form-textarea
                      rows="10"
                      v-model="rawResult"
                      id="description"
                      name="description"
                      label="Raw result"
                      :disabled="true"
                    ></form-textarea>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- This example requires Tailwind CSS v2.0+ -->
          <div
            v-if="results"
            class="bg-white shadow overflow-hidden sm:rounded-lg"
          >
            <div class="px-4 py-5 sm:px-6">
              <h3 class="text-lg leading-6 font-medium text-gray-900">
                Query results
              </h3>
              <p class="mt-1 max-w-2xl text-sm text-gray-500">
                {{ queryText }}
              </p>
            </div>
            <div class="border-t border-gray-200 px-4 py-5 sm:px-6">
              <dl class="grid grid-cols-1 gap-x-4 gap-y-8 sm:grid-cols-2">
                <div class="sm:col-span-1">
                  <dt class="text-sm font-medium text-gray-500">Took</dt>
                  <dd class="mt-1 text-sm text-gray-900">
                    {{ results.took }}
                  </dd>
                </div>
                <div class="sm:col-span-1">
                  <dt class="text-sm font-medium text-gray-500">Timed Out</dt>
                  <dd class="mt-1 text-sm text-gray-900">
                    {{ results.timed_out }}
                  </dd>
                </div>
                <div class="sm:col-span-1">
                  <dt class="text-sm font-medium text-gray-500">Total Hits</dt>
                  <dd class="mt-1 text-sm text-gray-900">
                    {{ results.hits.total.value }}
                  </dd>
                </div>
                <div class="sm:col-span-1">
                  <dt class="text-sm font-medium text-gray-500">Max Score</dt>
                  <dd class="mt-1 text-sm text-gray-900">
                    {{ results.hits.max_score }}
                  </dd>
                </div>
              </dl>
            </div>
          </div>
        </div>
      </div>
    </div>
  </app>
</template>

<script>
import App from "../layouts/app";
import forEach from "lodash/forEach";
import results from "./_results";
import highlight from './_highlight'

export default {
  props: ["indices"],
  components: {
    App,
    results,
    highlight
  },
  watch: {
    queryText(newValue, oldValue) {
      const axios = require("axios");

      const options = {
        method: "POST",
        headers: {
          Authorization: "Bearer 3|AOczTk7IA6PSikmnAZ5nLJ7zFQ9ghJCeay8LdgZD",
          "Content-Type": "application/json",
        },
        data: this.createQuery(newValue),
        url: "http://proxy.localhost:8080/docs/_search",
      };

      const self = this;
      axios(options)
        .then(function (response) {
          self.results = response.data;
          self.rawResult = JSON.stringify(response.data, null, 2);
        })
        .catch(function (error) {
          console.log(error);
        });
    },
  },
  methods: {
    createQuery(queryText) {
      let res = this.query.replace("$QUERY", queryText);

      let query = JSON.parse(res);

      this.querySent = JSON.stringify(query, null, 2);

      return query;
    },
  },
  mounted() {
    let result = {};
    forEach(this.indices, (data, index) => {
      result[index] = { id: index, name: index };
    });
    this.indicesNames = result;
  },
  data: function () {
    return {
      indicesNames: {},
      index: "20210423065152_hvbmb",
      queryText: "",
      querySent: "",
      rawResult: "",
      query: JSON.stringify(
        {
          query: {
            fuzzy: {
              content: "$QUERY",
            },
          },
          highlight: {
            fields: {
              content: {
                force_source: true,
                pre_tags: ["<b>"],
                post_tags: ["</b>"],
              },
            },
          },
        },
        null,
        2
      ),
      results: null,
    };
  },
};
</script>

<style scoped>
</style>
