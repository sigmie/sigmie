<template>
  <div>
    <div class="sm:hidden">
      <label for="tabs" class="sr-only">Select a tab</label>
      <select
        id="tabs"
        name="tabs"
        class="block w-full focus:ring-indigo-500 focus:border-indigo-500 border-gray-300 rounded-md"
      >
        <option>Synonyms</option>

        <option>Stopwords</option>

        <option selected>Stemming</option>

        <option>Scores</option>
      </select>
    </div>
    <div class="hidden sm:block">
      <div class="border-b border-gray-200">
        <nav class="-mb-px flex" aria-label="Tabs">
          <inertia-link
            v-for="(link, index) in links"
            :key="index"
            :href="$route(link.name)"
            :class="
              $route().current() === link.name
                ? 'border-theme-orange-light-900 text-theme-orange-light-900'
                : ''
            "
            class="w-1/4 py-4 px-1 text-center border-b-2 font-medium text-sm"
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
