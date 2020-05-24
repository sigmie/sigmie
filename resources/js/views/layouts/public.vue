<template>
  <div class="min-h-full relative pb-20">
    <main class="mx-auto container m-0 w-256 max-w-full">
      <slot></slot>
    </main>

    <footer class="bottom-0 absolute text-sm w-full h-20">
      <div class="flex flex-col justify-center h-full px-10">
        <div>
          <span class="inline-block pr-4 text-gray-500" href>© 2019 MOS - Sigma s.r.o.</span>
          <a
            class="inline-block pr-4 text-gray-500"
            :href="$route('legal.terms')"
          >Terms of service</a>
          <a
            class="inline-block pr-4 text-gray-500"
            :href="$route('legal.privacy')"
          >Privacy policy</a>
          <a class="inline-block pr-4 text-gray-500" :href="$route('legal.cookie')">Cookie Policy</a>
        </div>
      </div>
    </footer>
  </div>
</template>

<script>
export default {};
</script>

<style scoped>
</style>
