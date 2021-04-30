<template>
  <div>
    <div class="sm:hidden">
      <label for="tabs" class="sr-only">Select a tab</label>
      <select
        id="tabs"
        name="tabs"
        class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md"
      >
        <option
          :selected="$route().current() === link.name"
          v-for="(link, index) in links"
          :key="index"
        >
          {{ link.name }}
        </option>
      </select>
    </div>
    <div class="hidden sm:block">
      <div class="border-b border-gray-200">
        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
          <inertia-link
            v-for="(link, index) in links"
            :key="index"
            :href="$route(link.name)"
            :class="
              $route().current() === link.name
                ? 'border-theme-orange-light-900 text-theme-orange-light-900'
                : ''
            "
            class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm"
            aria-current="page"
          >
            {{ link.text }}
          </inertia-link>
        </nav>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {
      links: [
        {
          text: "Mapping",
          name: "analysis.mapping",
        },
        {
          text: "Stemming",
          name: "analysis.stemming",
        },
        {
          text: "Synonyms",
          name: "analysis.synonyms",
        },
        {
          text: "Stopwords",
          name: "analysis.stopwords",
        },
        {
          text: "Scores",
          name: "analysis.scores",
        },
      ],
    };
  },
};
</script>

<style scoped>
</style>
