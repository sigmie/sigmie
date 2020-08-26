<template>
  <div>
    <div class="bg-white shadow overflow-hidden rounded-lg">
      <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
        <h3 class="text-lg leading-6 font-medium text-gray-900">Account</h3>
        <p class="mt-1 max-w-2xl text-sm leading-5 text-gray-500">Personal information</p>
      </div>

      <div class="sm:col-span-2">
        <dd class="mt-1text-sm leading-5 text-gray-900">
          <div class="block">
            <div class="flex items-center px-3 sm:px-6 py-4">
              <div class="min-w-0 flex-1 flex items-center">
                <div class="flex-shrink-0">
                  <img class="h-16 w-16 rounded-full" :src="data.avatar_url" alt />
                </div>
                <div class="min-w-0 flex-1 px-4 md:grid md:grid-cols-2 md:gap-4">
                  <div>
                    <div
                      class="text-sm leading-5 font-medium text-orange-500 truncate"
                    >{{ data.username }}</div>
                    <div class="mt-2 flex items-center text-sm leading-5 text-gray-500">
                      <svg
                        class="flex-shrink-0 mr-1.5 h-5 w-5 text-gray-400"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                      >
                        <path
                          d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"
                        />
                        <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                      </svg>
                      <span>{{ data.email }}</span>
                    </div>
                  </div>
                  <div class="hidden md:flex">
                    <div class="text-sm leading-5 self-center text-gray-500">
                      Created on
                      <time :datetime="data.created_at">{{ onlyDate(data.created_at) }}</time>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </dd>
      </div>
      <div>
        <dl>
          <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
            <dt class="text-sm leading-5 font-medium text-gray-500">Username</dt>
            <dd
              class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2"
            >{{ data.username }}</dd>
          </div>
          <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
            <dt class="text-sm leading-5 font-medium text-gray-500">Email</dt>
            <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">{{ data.email }}</dd>
          </div>
          <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
            <dt class="text-sm leading-5 font-medium text-gray-500">Password</dt>
            <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">*********</dd>
          </div>

          <!-- <div class="sm:col-span-2 py-5 px-6">
            <dt class="text-sm leading-5 mb-5 font-medium text-gray-500">Attachments</dt>
            <dd class="mt-1text-sm leading-5 text-gray-900">
              <div class="w-40">
                <button-danger text="Delete my account"></button-danger>
              </div>
            </dd>
          </div>-->
        </dl>
      </div>
    </div>
  </div>
</template>

<script>
import moment from "moment";

export default {
  props: ["data"],
  methods: {
    onlyDate(datetime) {
      return moment.utc(datetime).format("LL");
    },
  },
};
</script>

<style scoped>
</style>