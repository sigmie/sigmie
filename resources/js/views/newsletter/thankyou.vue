<template>
  <div class="min-h-full relative pb-20 gradient text-white">
    <main class="mx-auto container">
      <inertia-link
        :href="$route('landing')"
        class="float-right mt-10 cursor-pointer mr-10 sm:mr-10 md:mr-0 lg:mr-0 pr-5"
      >
        <icon-x fill="#ffffff" height="25px" />
      </inertia-link>

      <div class="max-w-3xl mx-auto px-6 py-8 sm:py-16 md:py-24">
        <div class="mt-20">
          <h1 class="font-display font-semibold text-4xl sm:text-5xl mb-8 leading-none">
            Thank you! Now
            check your email.
          </h1>
          <p class="text-xl sm:text-2xl leading-normal">
            You should get a confirmation email soon,
            open it up and
            <strong
              class="font-bold"
            >click the confirm email button</strong> so I
            can keep you up to date.
          </p>
        </div>
      </div>
    </main>
  </div>
</template>

<script>
export default {};
</script>

<style scoped>
</style>
