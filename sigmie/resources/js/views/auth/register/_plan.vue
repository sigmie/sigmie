<template>
  <div class="md:px-4 pt-10 pb-5 sm:pb-0 md:pt-0 md:w-full mx-auto self-center">
    <div class="bg-white overflow-hidden shadow-md sm:rounded-lg">
      <div class="px-8 sm:px-12 pb-5 pt-10 md:px-14 md:pb-6">
        <div class="flex flex-col sm:w-96">
          <div
            class="flex flex-row flex-wrap-dreverse justify-between mx-0 border-b border-gray-200 pb-6 md:pb-3"
          >
            <div class="flex-col justify-center w-full">
              <div class>
                <illustration class="h-32 mb-5 mx-auto"></illustration>
              </div>
              <h2 class="font-semibold text-gray-800 text-xl">$12 a month</h2>
              <p
                class="leading-none text-gray-500 font-normal text-xs"
              >Price does not include your Cloud Provider costs.</p>
              <span></span>
            </div>
          </div>
          <div class="flex-col md:flex-row flex md:space-x-4 justify-between md:pt-8">
            <div class="flex-1 mx-0 mt-6 md:mt-0">
              <ul class="text-black">
                <li class="text-sm leading-5 flex mb-3">
                  <div>
                    <icon-check class="w-5 text-white bg-green-400 rounded-full mr-3"></icon-check>
                  </div>
                  <div>
                    <h4 class="font-semibold text-sm text-gray-700 mb-1">14 day free trial</h4>
                    <p class="text-gray-500 text-sm">
                      Try out all our features and if you aren't satisfied
                      you will still keep your cluster.
                    </p>
                  </div>
                </li>
                <li class="text-sm leading-5 flex mb-3">
                  <div>
                    <icon-check class="w-5 text-white bg-green-400 rounded-full mr-3"></icon-check>
                  </div>
                  <div>
                    <h4 class="font-semibold text-sm text-gray-700 mb-1">Alerts</h4>
                    <p
                      class="text-gray-500 text-sm"
                    >Get notifications regarding significant changes via Email, Slack and SMS.</p>
                  </div>
                </li>
                <li class="text-sm leading-5 flex mb-3">
                  <div>
                    <icon-check class="w-5 text-white bg-green-400 rounded-full mr-3"></icon-check>
                  </div>
                  <div>
                    <h4 class="font-semibold text-sm text-gray-700 mb-1">Cloud Providers</h4>
                    <p
                      class="text-gray-500 text-sm"
                    >Choose your desired Cloud Provider between Google Cloud, AWS, Digital Ocean and Microsoft Azure.</p>
                  </div>
                </li>
                <li class="text-sm leading-5 flex mb-3">
                  <div>
                    <icon-check class="w-5 text-white bg-green-400 rounded-full mr-3"></icon-check>
                  </div>
                  <div>
                    <h4 class="font-semibold text-sm text-gray-700 mb-1">Proxy</h4>
                    <p
                      class="text-gray-500 text-sm"
                    >Make 50.000 requests each month, by using your access tokens.</p>
                  </div>
                </li>
                <li class="text-sm leading-5 flex mb-3">
                  <div>
                    <icon-check class="w-5 text-white bg-green-400 rounded-full mr-3"></icon-check>
                  </div>
                  <div>
                    <h4 class="font-semibold text-sm text-gray-700 mb-1">Playground</h4>
                    <p
                      class="text-gray-500 text-sm"
                    >Query your search, and recieve analysis regarding your search results.</p>
                  </div>
                </li>
                <li class="text-sm leading-5 flex mb-3">
                  <div>
                    <icon-check class="w-5 text-white bg-green-400 rounded-full mr-3"></icon-check>
                  </div>
                  <div>
                    <h4 class="font-semibold text-sm text-gray-700 mb-1">Multiple environments</h4>
                    <p
                      class="text-gray-500 text-sm"
                    >Create a test and staging environment for your development workflow.</p>
                  </div>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import illustration from "./_illustration";

export default {
  components: {
    illustration
  },
};
</script>

<style scoped>
</style>