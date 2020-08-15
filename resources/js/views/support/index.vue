<template>
  <app title="Support">
    <div class="flex md:items-center md:justify-between md:mb-4">
      <div class="flex-1 max-w-md mx-auto">
        <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
          <h1 class="text-lg leading-6 font-medium text-gray-900">Have a Question ?</h1>
          <form-textarea placeholder="What's your question?" class="pb-10" name="description" type="text"></form-textarea>
          <button-primary text="Send"></button-primary>
        </div>
      </div>
    </div>
  </app>
</template>

<script>
import App from "../layouts/app";

export default {
  components: {
    App,
  },
};
</script>

<style scoped>
</style>