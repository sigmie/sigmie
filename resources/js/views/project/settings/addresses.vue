<template>
  <div>
    <modal
      title="Add Address"
      primaryText="Create"
      secondaryText="Cancel"
      @primaryAction="store"
      @secondaryAction="closeCreateForm"
      @clickAway="closeCreateForm"
      @onEsc="closeCreateForm"
      @onEnter="store"
      :icon="false"
      :show="createRequest"
      type="info"
    >
      <form
        @submit.prevent="store"
        v-on:keydown.enter.prevent="store"
        class="space-y-3 w-full"
      >
        <form-input
          id="name"
          type="text"
          v-model="createForm.name"
          :errors="createForm.errors.name"
          label="Name"
          placeholder="Office"
        ></form-input>

        <form-input
          id="ip"
          type="text"
          v-model="createForm.ip"
          :errors="createForm.errors.ip"
          placeholder="eg. 199.27.25.0"
          label="IP Address"
        ></form-input>
      </form>
    </modal>
    <modal
      title="Edit Address"
      primaryText="Update"
      secondaryText="Cancel"
      actionIcon="trash"
      @iconAction="destroy"
      @primaryAction="update"
      @secondaryAction="closeUpdateForm"
      @clickAway="closeUpdateForm"
      @onEsc="closeUpdateForm"
      @onEnter="update"
      :icon="false"
      :show="editRequest"
      type="info"
    >
      <form
        @submit.prevent="update"
        v-on:keydown.enter.prevent="update"
        class="space-y-3 w-full"
      >
        <form-input
          id="name"
          type="text"
          v-model="updateForm.name"
          :errors="updateForm.errors.name"
          label="Name"
          placeholder=""
        ></form-input>

        <form-input
          id="ip"
          type="text"
          v-model="updateForm.ip"
          :errors="updateForm.errors.ip"
          placeholder=""
          label="IP Address"
        ></form-input>
      </form>
    </modal>
    <div
      class="pt-5 shadow mx-auto bg-white rounded-md sm:overflow-hidden max-w-lg mt-6"
    >
      <div class="">
        <div class="flex items-center justify-between px-6 mb-3">
          <h3 class="text-md self-start leading-6 font-medium text-gray-900">
            Authorized Addresses
          </h3>
          <div class="ml-3 h-7 flex items-center"></div>
        </div>

        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50 min-w-full border-t border-b">
            <tr>
              <th
                scope="col"
                class="pl-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
              >
                Name
              </th>
              <th
                scope="col"
                class="py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
              >
                IP
              </th>
              <th
                scope="col"
                class="py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
              >
                <!-- Actions -->
              </th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr :key="index" v-for="(address, index) in ips">
              <td class="pl-6 py-4 whitespace-nowrap text-sm text-gray-500">
                {{ address.name }}
              </td>
              <td
                class="py-4 whitespace-nowrap text-sm w-3/5 font-medium text-gray-900"
              >
                {{ address.ip }}
              </td>
              <td
                class="pr-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"
              >
                <button
                  :disabled="disabled"
                  @click="edit(address)"
                  type="button"
                  :class="
                    disabled
                      ? 'text-theme-orange-light-500 cursor-not-allowed'
                      : 'text-theme-orange-light-900 hover:text-theme-orange-light-800 '
                  "
                  class="bg-white rounded-md font-medium focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500"
                >
                  <icon-edit class="h-6"></icon-edit>
                </button>
              </td>
            </tr>
            <tr>
              <td
                colspan="3"
                class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"
              >
                <button-secondary
                  :disabled="disabled"
                  @click="create"
                  :text="disabled ? 'Updating...' : 'Add IP Address'"
                ></button-secondary>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  props: ["ips", "clusterId", "disabled"],
  data() {
    return {
      createRequest: false,
      deleteRequest: false,
      editRequest: false,
      createForm: this.$inertia.form({
        ip: "",
        name: "",
      }),
      updateForm: this.$inertia.form({
        id: "",
        ip: "",
        name: "",
      }),
    };
  },
  methods: {
    closeUpdateForm() {
      this.updateForm.clearErrors();
      this.editRequest = false;
    },
    closeCreateForm() {
      this.createRequest = false;
    },
    create() {
      this.createRequest = true;
    },
    edit(address) {
      this.updateForm.name = address.name;
      this.updateForm.ip = address.ip;
      this.updateForm.id = address.id;
      this.editRequest = true;
    },
    store() {
      const route = this.$route("cluster.allowed-ips.store", {
        cluster: this.clusterId,
      });

      this.createForm.post(route, {
        onSuccess: () => {
          this.createForm.reset();
          this.closeCreateForm();
          this.$inertia.reload({ only: cluster });
        },
      });
    },
    update() {
      const route = this.$route("cluster.allowed-ips.update", {
        cluster: this.clusterId,
        address: this.updateForm.id,
      });

      this.updateForm.put(route, {
        onSuccess: () => {
          this.closeUpdateForm();
          this.$inertia.reload({ only: ["cluster"] });
        },
      });
    },
    destroy() {
      const route = this.$route("cluster.allowed-ips.destroy", {
        cluster: this.clusterId,
        address: this.updateForm.id,
      });

      this.$inertia.delete(route);

      this.closeUpdateForm();
    },
  },
};
</script>

<style scoped>
</style>
