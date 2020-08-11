<template>
  <section class="bg-white px-16 md:py-20">
    <div class="container mx-auto lg:w-8/12 md:px-4">
      <div class="flex flex-col-reverse md:flex-col">
        <div
          class="flex flex-row flex-wrap-dreverse justify-between w-full mx-auto md:mx-0 mt-8 md:mt-0 md:pt-0 pt-8 border-t-2 border-b-0 md:border-t-0 md:border-b-2 border-gray-200 pb-10"
        >
          <div class="flex mx-auto w-2/3 md:mx-0 py-5">
            <h2 class="font-bold text-gray-800 text-xl md:text-3xl">
              Starting at $12 a month
              <span
                class="hidden md:inline-block leading-none text-gray-400 font-normal text-sm"
              >/ per project.</span>
              <span
                class="block md:hidden leading-none text-gray-400 font-normal text-sm"
              >per project.</span>
            </h2>
          </div>
          <div class="flex mx-auto w-1/3 justify-end md:mx-0">
            <inertia-link
              :href="$route('register')"
              class="bg-theme-secondary-lighter md:w-48 flex items-center justify-center px-4 rounded-lg h-12 self-center text-sm leading-5 text-white"
            >Get started</inertia-link>
          </div>
        </div>
        <div class="flex-col md:flex-row flex md:space-x-4 justify-between pt-8">
          <div class="md:w-1/3 mx-auto flex-1 md:mx-0">
            <h3 class="font-bold text-gray-800 text-3xl mb-4">All-in-One</h3>
            <p class="font-normal text-base text-cool-gray-400">
              Simple and affordable pricing. Since the infastructure remains on your side,
              you don't have to worry about pricing units.
            </p>
          </div>
          <div class="md:w-1/3 mx-auto flex-1 md:mx-0 mt-8 md:mt-0">
            <ul class="text-black">
              <li class="text-sm leading-5 mb-4 flex">
                <div>
                  <icon-check class="w-6 rounded-full text-green-400 mr-3"></icon-check>
                </div>
                <div>
                  <h4 class="font-semibold text-base text-gray-700 mb-1">14 day free trial</h4>
                  <p class="text-gray-400 text-sm">
                    Try out all our features and if you aren't satisfied
                    your still get to keep your cluster.
                  </p>
                </div>
              </li>
              <li class="text-sm leading-5 mb-4 flex">
                <div>
                  <icon-check class="w-6 text-green-500 mr-3"></icon-check>
                </div>
                <div>
                  <h4 class="font-semibold text-base text-gray-700 mb-1">Alerts</h4>
                  <p
                    class="text-gray-400 text-sm"
                  >Get notifications regarding significant changes via Email, Slack and SMS.</p>
                </div>
              </li>
              <li class="text-sm leading-5 mb-4 flex">
                <div>
                  <icon-check class="w-6 text-green-500 mr-3"></icon-check>
                </div>
                <div>
                  <h4 class="font-semibold text-base text-gray-700 mb-1">Cloud Providers</h4>
                  <p
                    class="text-gray-400 text-sm"
                  >Choose your desired Cloud Provider between Google Cloud, AWS, Digital Ocean and Microsoft Azure.</p>
                </div>
              </li>
            </ul>
          </div>
          <div class="md:w-1/3 mx-auto flex-1 md:mx-0">
            <ul class="text-black">
              <li class="text-sm leading-5 mb-4 flex flex-">
                <div>
                  <icon-check class="w-6 text-green-500 mr-3"></icon-check>
                </div>
                <div>
                  <h4 class="font-semibold text-base text-gray-700 mb-1">Proxy</h4>
                  <p
                    class="text-gray-400 text-sm"
                  >Make 50.000 requests each month, by using your access tokens.</p>
                </div>
              </li>
              <li class="text-sm leading-5 mb-4 flex">
                <div>
                  <icon-check class="w-6 text-green-500 mr-3"></icon-check>
                </div>
                <div>
                  <h4 class="font-semibold text-base text-gray-700 mb-1">Playground</h4>
                  <p
                    class="text-gray-400 text-sm"
                  >Query your search, and recieve analysis regarding your search results.</p>
                </div>
              </li>
              <li class="text-sm leading-5 mb-4 flex">
                <div>
                  <icon-check class="w-6 text-green-500 mr-3"></icon-check>
                </div>
                <div>
                  <h4 class="font-semibold text-base text-gray-700 mb-1">Multiple environment</h4>
                  <p
                    class="text-gray-400 text-sm"
                  >Create a test and staging environment for your development workflow.</p>
                </div>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script>
export default {};
</script>

<style scoped>
</style>