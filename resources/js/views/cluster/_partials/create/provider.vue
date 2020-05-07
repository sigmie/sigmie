<template>
  <div class="pt-4">
    <div class="md:grid md:grid-cols-3 md:gap-6">
      <div class="mt-5 md:mt-0 md:col-span-2">
        <div class="shadow sm:rounded-md sm:overflow-hidden">
          <div v-if="state === 'chosen'" class="px-4 py-5 bg-white sm:p-6">chosen</div>
          <div v-if="state === 'choosing'" class="px-4 py-5 bg-white sm:p-6">
            <ul class>
              <li>
                <a
                  href="#"
                  class="block hover:bg-gray-50 focus:outline-none focus:bg-gray-50 transition duration-150 ease-in-out"
                >
                  <div class="flex items-center px-4 py-4 sm:px-6">
                    <div class="min-w-0 flex-1 flex items-center">
                      <div class="flex-shrink-0 w-16">
                        <img class="h-10 w-auto mx-auto" src="/img/gcloud_logo.png" alt />
                      </div>
                      <div class="min-w-0 flex-1 flex items-center pl-4">
                        <p>Google Cloud Platform</p>
                      </div>
                    </div>
                    <div>
                      <button-secondary @click="choose('google')" text="Choose"></button-secondary>
                    </div>
                  </div>
                </a>
              </li>
              <li class="border-t border-gray-100">
                <a
                  href="#"
                  class="block hover:bg-gray-50 focus:outline-none focus:bg-gray-50 transition duration-150 ease-in-out"
                >
                  <div class="flex items-center px-4 py-4 sm:px-6">
                    <div class="min-w-0 flex-1 flex items-center">
                      <div class="flex-shrink-0 w-16">
                        <img class="h-10 w-auto mx-auto" src="/img/aws_logo.png" alt />
                      </div>
                      <div class="min-w-0 flex-1 flex items-center pl-4">
                        <p>Amazon Web Services</p>
                      </div>
                    </div>
                    <div>
                      <button-disabled text="Comming"></button-disabled>
                      <!-- <button-secondary text="Choose"></button-secondary > -->
                    </div>
                  </div>
                </a>
              </li>
              <li class="border-t border-gray-100">
                <a
                  href="#"
                  class="block hover:bg-gray-50 focus:outline-none focus:bg-gray-50 transition duration-150 ease-in-out"
                >
                  <div class="flex items-center px-4 py-4 sm:px-6">
                    <div class="min-w-0 flex-1 flex items-center">
                      <div class="flex-shrink-0 w-16">
                        <img class="h-10 w-auto mx-auto" src="/img/do_logo.png" alt />
                      </div>
                      <div class="min-w-0 flex-1 flex items-center pl-4">
                        <p>DigitalOcean</p>
                      </div>
                    </div>
                    <div>
                      <button-disabled text="Comming"></button-disabled>
                      <!-- <button-secondary text="Choose"></button-secondary > -->
                    </div>
                  </div>
                </a>
              </li>
            </ul>
          </div>
        </div>
      </div>
      <div class="md:col-span-1">
        <div class="px-4 sm:px-0">
          <h3 class="text-lg font-medium leading-6 text-gray-900">Cloud provider</h3>
          <p
            class="mt-1 text-sm leading-5 text-gray-600"
          >Your Cloud provider for your Elasticsearch cluster.</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {
      provider: "",
      state: "choosing"
    };
  },
  methods: {
    choose(provider) {
      this.provider = provider;
      this.state = "chosen";
    }
  }
};
</script>

<style>
</style>
