<template>
  <form>
    <div class="border-b border-gray-200">
      <heading-card class="py-5" text="Sign up" />
    </div>
    <div class="p-6">
      <div class="border-gray-200">
        <div>
          <h3 class="text-lg leading-6 font-medium text-gray-900">Account</h3>
          <p class="mt-1 max-w-2xl text-sm leading-5 text-gray-500">Your account informations</p>
        </div>
        <div class="sm:col-span-3 pb-2">
          <form-input class="pt-4" label="Email address" />
        </div>
        <div class="sm:col-span-3 pb-2">
          <form-input class="pt-4" label="Password" />
        </div>
        <div class="sm:col-span-3 pb-2">
          <form-input class="pt-4" label="Password confirm" />
        </div>
      </div>
    </div>
    <div class="mt-2 border-t border-gray-200 p-6">
      <div>
        <h3 class="text-lg leading-6 font-medium text-gray-900">Billing</h3>
        <p class="mt-1 max-w-2xl text-sm leading-5 text-gray-500">Account billing informations</p>
      </div>

      <div class="mt-6 bg-white">
        <fieldset>
          <legend class="block text-sm font-medium leading-5 text-gray-700">Plan</legend>
          <div class="mt-1 rounded-md shadow-sm">
            <div>
              <select
                aria-label="Country"
                class="form-select relative block w-full rounded-none rounded-md bg-transparent focus:z-10 transition ease-in-out duration-150 sm:text-sm sm:leading-5"
              >
                <option>Hobby</option>
                <option>Serious</option>
                <option>Pro</option>
              </select>
            </div>
          </div>
        </fieldset>
      </div>
      <div class="pt-4">
        <div class="sm:col-span-3 pb-2">
          <form-input class="pt-4" label="Name" />
        </div>
      </div>
      <div class="pt-4">
        <div class="sm:col-span-3 pb-2">
          <stripe />
        </div>
      </div>
    </div>
    <div class="border-t border-gray-200 p-5">
      <div class="flex justify-end">
        <span class="ml-3 inline-flex rounded-md shadow-sm">
          <button-primary text="Register" type="submit" />
        </span>
      </div>
    </div>
  </form>
</template>

<script>
export default {};
</script>

<style>
</style>
