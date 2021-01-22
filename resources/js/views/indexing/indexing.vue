<template>
  <app>
    <div class="mt-8">
      <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">

        <plans></plans>

        </div>
      </div>

      <h2
        class="max-w-6xl mx-auto mt-8 mb-4 px-4 text-lg leading-6 font-medium text-gray-900 sm:px-6 lg:px-8"
      >
        Recent activity
      </h2>

        <activities></activities>
        <create-form></create-form>
    </div>
  </app>
</template>

<script>
import App from "../layouts/app";
import Plans from "./_plans";
import Activities from "./_activities";
import CreateForm from './_form';

export default {
  components: {
    App,
    Plans,
    Activities,
    CreateForm
  },
};
</script>

<style scoped>
</style>
