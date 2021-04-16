<template>
  <div class="bg-white shadow overflow-hidden rounded-lg">
    <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
      <h3 class="text-lg leading-6 font-medium text-gray-900">Notifications</h3>
      <p class="mt-1 max-w-2xl text-sm leading-5 text-gray-500">
        Choose the type of notifications you want to recieve.
      </p>
    </div>

    <ul class="grid grid-cols-1 gap-x-4 gap-y-8 sm:grid-cols-2 px-12 py-6">
      <li class="col-span-1 flex px-4">
        <form-checkbox
          v-model="form.reports"
          id="reports"
          name="reports"
          label="Weekly Reports"
        ></form-checkbox>
      </li>
      <li class="col-span-1 flex px-4">
        <form-checkbox
          v-model="form.alerts"
          id="alerts"
          name="alerts"
          label="Usage Alerts"
        ></form-checkbox>
      </li>
      <li class="col-span-1 flex px-4">
        <form-checkbox
          v-model="form.payments"
          id="payments"
          name="payments"
          label="Upcoming Payments"
        ></form-checkbox>
      </li>

      <li class="col-span-1 flex px-4">
        <form-checkbox
          v-model="form.features"
          id="features"
          name="features"
          label="New Features"
        ></form-checkbox>
      </li>

      <li class="col-span-2 flex justify-between px-4 border-t pt-6">
        <button
          type="button"
          @click="reset"
          class="text-sm py-2 px-4 cursor-pointer leading-5 text-gray-500"
        >
          Discard Changes
        </button>
        <div>
          <button-primary
            :disabled="false"
            @click="submit"
            type="submit"
            text="Save Changes"
          ></button-primary>
        </div>
      </li>
    </ul>
  </div>
</template>

<script>
export default {
  props: ["data"],
  data() {
    return {
      form: this.$inertia.form({
        features: false,
        reports: true,
        alerts: false,
        payments: false,
      }),
    };
  },
  methods: {
    reset() {
      this.form.reset();
    },
    async submit() {
      await new Promise((r) => setTimeout(r, 2000));
      this.form.reset();
    },
  },
};
</script>

<style scoped>
</style>
