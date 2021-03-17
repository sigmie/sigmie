<template>
  <div>
    <div
      class="pt-5 shadow mx-auto bg-white rounded-md sm:overflow-hidden max-w-lg mt-6"
    >
      <div class="">
        <!-- <h3 class="px-6 text-lg leading-6 font-medium text-gray-900">Security</h3> -->

        <p class="px-6 text-md mb-3 leading-6 font-medium text-gray-900">
          Authorized networks
        </p>

        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50 border-t border-b">
            <tr>
              <th
                scope="col"
                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
              >
                Name
              </th>
              <th
                scope="col"
                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
              >
                IP
              </th>
              <th
                scope="col"
                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
              >
                <!-- Actions -->
              </th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr :key="name" v-for="(ip, name) in ips">
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                {{ name }}
              </td>
              <td
                class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"
              >
                {{ ip }}
              </td>
              <td
                class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"
              >
                <button
                  type="button"
                  class="bg-white rounded-md font-medium text-theme-orange-light-900 hover:text-theme-orange-light-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500"
                >
                  Update
                </button>
                <span class="text-gray-300 px-2" aria-hidden="true">|</span>
                <button
                  type="button"
                  class="bg-white rounded-md font-medium text-theme-orange-light-900 hover:text-theme-orange-light-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500"
                >
                  Remove
                </button>
              </td>
            </tr>
            <tr v-if="createRequest === false">
              <td
                colspan="3"
                class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"
              >
                <button-secondary
                  @click="createRequest = true"
                  text="Add IP Address"
                ></button-secondary>
              </td>
            </tr>
          </tbody>
        </table>

        <form v-if="createRequest" class="px-10 border-t py-5 space-y-3">
          <form-input
            id="name"
            type="text"
            v-model="form.name"
            :errors="form.errors.name"
            label="Name"
            placeholder="Office"
          ></form-input>

          <form-input
            id="ip"
            type="text"
            v-model="form.ip"
            :errors="form.errors.ip"
            placeholder="eg. 199.27.25.0"
            label="IP Address"
          ></form-input>

          <div class="flex justify-end">
            <div class="w-3/5 flex">
              <button-primary
                :disabled="form.processing"
                type="submit"
                text="Save"
              ></button-primary>
              <button-secondary
                class="ml-5"
                @click="createRequest = false"
                text="Cancel"
              ></button-secondary>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  props: ["ips"],
  data() {
    return {
      createRequest: true,
      deleteRequest: false,
      form: this.$inertia.form({
        ip: "",
        name: "",
      }),
    };
  },
  methods: {},
};
</script>

<style scoped>
</style>
