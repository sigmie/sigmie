<template>
  <div class="text-center">
    <div class="pb-3 mx-auto md:mx-0 block px-3">
      <inertia-link :href="$route('legal.about')">About Us</inertia-link>
    </div>
    <div class="pb-3 mx-auto block px-3">
      <inertia-link :href="$route('legal.terms')">Terms of Service</inertia-link>
    </div>
    <div class="pb-3 mx-auto block px-3">
      <inertia-link :href="$route('legal.privacy')">Privacy Policy</inertia-link>
    </div>
    <div class="pb-3 mx-auto block px-3">
      <inertia-link :href="$route('legal.imprint')">Imprint</inertia-link>
    </div>
    <div class="pb-3 mx-auto block px-3">
      <inertia-link :href="$route('legal.disclaimer')">Disclaimer</inertia-link>
    </div>
  </div>
</template>

<script>
export default {};
</script>

<style scoped>
</style>