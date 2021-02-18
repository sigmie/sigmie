<template>
  <li>
    <button
      @click="activity.description ? (expand = !expand) : null"
      class="block w-full hover:bg-gray-50"
    >
      <div class="px-4 py-4 flex items-center sm:px-6">
        <div class="min-w-0 flex-1 sm:flex sm:items-center sm:justify-between">
          <div>
            <div
              :class="
                activity.type === 'info'
                  ? 'text-theme-orange-light-800'
                  : activity.type === 'error'
                  ? 'text-red-600'
                  : null
              "
              class="flex text-sm font-medium truncate"
            >
              <p>
                {{ activity.title }}
              </p>
            </div>
            <div class="mt-2 flex">
              <div class="flex items-center text-sm text-gray-500">
                <!-- Heroicon name: calendar -->
                <svg
                  class="flex-shrink-0 mr-1.5 h-5 w-5 text-gray-400"
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 20 20"
                  fill="currentColor"
                  aria-hidden="true"
                >
                  <path
                    fill-rule="evenodd"
                    d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z"
                    clip-rule="evenodd"
                  />
                </svg>
                <p>
                  Happend on
                  <time>{{ humanDate(activity.timestamp) }}</time>
                </p>
              </div>
            </div>
          </div>
        </div>
        <div v-if="activity.description" class="ml-5 flex-shrink-0">
          <icon-cheveron-down
            class="text-gray-400 h-4 w-4"
          ></icon-cheveron-down>
        </div>
      </div>
    </button>
    <div
      v-if="expand"
      class="border-t bg-gray-50 border-gray-200 px-4 py-5 sm:px-6"
    >
      <dl class="grid grid-cols-1 gap-x-4 gap-y-8 sm:grid-cols-2">
        <div class="sm:col-span-1">
          <dt class="text-sm font-medium text-gray-500">Full name</dt>
          <dd class="mt-1 text-sm text-gray-900">Margot Foster</dd>
        </div>
        <div class="sm:col-span-1">
          <dt class="text-sm font-medium text-gray-500">Application for</dt>
          <dd class="mt-1 text-sm text-gray-900">Backend Developer</dd>
        </div>
        <div class="sm:col-span-1">
          <dt class="text-sm font-medium text-gray-500">Email address</dt>
          <dd class="mt-1 text-sm text-gray-900">margotfoster@example.com</dd>
        </div>
        <div class="sm:col-span-1">
          <dt class="text-sm font-medium text-gray-500">Salary expectation</dt>
          <dd class="mt-1 text-sm text-gray-900">$120,000</dd>
        </div>
        <div class="sm:col-span-2">
          <dt class="text-sm font-medium text-gray-500">About</dt>
          <dd class="mt-1 text-sm text-gray-900">
            Fugiat ipsum ipsum deserunt culpa aute sint do nostrud anim
            incididunt cillum culpa consequat. Excepteur qui ipsum aliquip
            consequat sint. Sit id mollit nulla mollit nostrud in ea officia
            proident. Irure nostrud pariatur mollit ad adipisicing reprehenderit
            deserunt qui eu.
          </dd>
        </div>
      </dl>
    </div>
  </li>
</template>

<script>
import moment from "moment";
export default {
  props: ["activity"],
  methods: {
    humanDate(utcDatetime) {
      return (
        moment.utc(utcDatetime).local().format("LL") +
        " at " +
        moment.utc(utcDatetime).local().format("HH:mm")
      );
    },
  },
  data() {
    return {
      expand: false,
    };
  },
};
</script>

<style scoped>
</style>
