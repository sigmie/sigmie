<template>
  <div
    class="pt-5 shadow mx-auto bg-white rounded-md sm:overflow-hidden max-w-lg mt-6"
  >
    <div class="">
      <div class="mb-3 px-6">
        <h3 class="text-md self-start leading-6 font-medium text-gray-900">
          Authentication
        </h3>
      </div>
      <div class="grid grid-cols-1 gap-x-4 sm:grid-cols-1 border-t">
        <div class="sm:col-span-1 grid grid-cols-2 gap-x-4 gap-y-2 px-6 py-4">
          <div
            class="col-span-2 md:col-span-1 flex text-sm font-medium text-gray-500"
          >
            <label class="self-center" for="username">Username:</label>
          </div>

          <form-input
            class="col-span-2 md:col-span-1"
            v-if="state === STATE_EDIT"
            id="username"
            type="text"
            label=""
            :value="username"
          ></form-input>

          <div
            v-else
            class="col-span-2 md:col-span-1 font-semibold text-base text-gray-900"
          >
            {{ username }}
          </div>
        </div>
        <div
          class="sm:col-span-1 grid bg-gray-50 grid-cols-2 gap-x-4 gap-y-2 px-6 py-4"
        >
          <div
            class="col-span-2 md:col-span-1 flex text-sm font-medium text-gray-500"
          >
            <label class="self-center" for="password">Password:</label>
          </div>

          <form-input
            class="col-span-2 md:col-span-1"
            v-if="state === STATE_EDIT"
            id="password"
            type="text"
            label=""
            value=""
            placeholder="New password"
          ></form-input>
          <div
            v-else
            class="col-span-2 md:col-span-1 font-semibold text-base italic text-gray-600"
          >
            **************
          </div>
        </div>
      </div>

      <div class="flex justify-end px-6 py-4">
        <div class="w-full md:w-auto" v-if="state === STATE_NONE">
          <button-secondary
            @click="edit"
            text="Edit authentication"
          ></button-secondary>
        </div>
        <div class="w-full md:w-auto pr-1 md:pr-2" v-if="state === STATE_EDIT">
          <button-secondary text="Cancel" @click="cancel"></button-secondary>
        </div>
        <div class="w-full md:w-auto pl-1 md:p-0" v-if="state === STATE_EDIT">
          <button-primary text="Update"></button-primary>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
const STATE_EDIT = "EDIT ";
const STATE_NONE = "NONE";
export default {
  created() {
    this.STATE_EDIT = STATE_EDIT;
    this.STATE_NONE = STATE_NONE;
  },
  data() {
    return {
      state: STATE_NONE,
    };
  },
  props: ["username"],
  methods: {
    cancel() {
      this.state = this.STATE_NONE;
    },
    edit() {
      this.state = this.STATE_EDIT;
    },
  },
};
</script>

<style scoped>
</style>
