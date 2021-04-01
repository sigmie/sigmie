<template>
  <div
    class="pt-5 shadow mx-auto bg-white rounded-md sm:overflow-hidden max-w-lg mt-6"
  >
    <form @submit.prevent="submit">
      <div class="mb-3 px-6">
        <h3 class="text-md self-start leading-6 font-medium text-gray-900">
          Basic Authentication
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
            v-model="updateForm.username"
            :errors="updateForm.errors.username"
            id="username"
            type="text"
            label=""
          ></form-input>

          <div
            v-else
            class="col-span-2 md:col-span-1 font-semibold text-base text-gray-900"
          >
            {{ cluster.username }}
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
            v-model="updateForm.password"
            :errors="updateForm.errors.password"
            id="password"
            type="text"
            label=""
            value=""
            placeholder="New password"
          ></form-input>
          <div
            v-else
            class="col-span-2 md:col-span-1 font-normal text-sm italic text-gray-500"
          >
            hidden
          </div>
        </div>
      </div>

      <div class="flex justify-end px-6 py-4">
        <div class="w-full md:w-auto" v-if="state === STATE_NONE">
          <button-secondary
            :disabled="cluster.can_update_basic_auth === false"
            @click="edit"
            :text="
              cluster.can_update_basic_auth
                ? 'Edit authentication'
                : 'Updating...'
            "
          ></button-secondary>
        </div>
        <div class="w-full md:w-auto pr-1 md:pr-2" v-if="state === STATE_EDIT">
          <button-secondary text="Cancel" @click.prevent="cancel"></button-secondary>
        </div>
        <div class="w-full md:w-auto pl-1 md:p-0" v-if="state === STATE_EDIT">
          <button-primary type="submit" text="Update"></button-primary>
        </div>
      </div>
    </form>
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
      updateForm: this.$inertia.form({
        username: this.cluster.username,
        password: "",
      }),
    };
  },
  props: ["cluster"],
  methods: {
    submit() {
      const route = this.$route("cluster.basic-auth.update", {
        cluster: this.cluster.id,
      });

      this.updateForm.put(route, {
        onSuccess: () => {
          this.state = STATE_NONE;
          this.$inertia.reload({ only: ["cluster"] });
          this.updateForm.password = "";
        },
      });
    },
    cancel() {
      this.updateForm.clearErrors();
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
