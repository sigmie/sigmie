<template>
  <div
    class="border-gray-200 w-full bg-white shadow overflow-hidden rounded-lg"
  >
    <div class="flex justify-between px-6 py-5">
      <div class>
        <div class="font-semibold text-base text-gray-800">Billing Address</div>
        <div class="text-sm text-gray-600">
          We will send your Paddle invoice to this email.
        </div>
      </div>
      <div class="max-w-sm py-1">
        <div class="mt-1 relative rounded-md shadow-sm">
          <input
            type="text"
            v-model="form.email"
            name="email"
            id="email"
            class="block w-full pr-8 border-gray-300 text-gray-500 placeholder-gray-300 focus:outline-none focus:text-gray-500 sm:text-sm rounded-md"
            placeholder="invoice@example.com"
          />
          <div
            v-if="active || loading"
            class="absolute inset-y-0 right-0 pr-2 flex items-center pointer-events-none"
          >
            <icon-loading
              v-if="loading"
              class="h-5 w-5 text-gray-800"
            ></icon-loading>
            <icon-check v-else class="h-5 w-5 text-green-500"></icon-check>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  watch: {
    "form.email": function (newValue, oldValue) {
      if (this.validateEmail(newValue)) {
        this.update();
      }

      if (newValue === "") {
        this.update();
      }
    },
  },
  data() {
    return {
      form: this.$inertia.form({
        email: "",
      }),
      loading: false,
      active: false,
    };
  },
  methods: {
    validateEmail(email) {
      var re = /\S+@\S+\.\S+/;
      return re.test(email);
    },
    async update() {
      this.loading = true;
      await new Promise((r) => setTimeout(r, 2000));
      this.loading = false;

      if (this.form.email === "") {
        this.active = false;
      } else {
        this.active = true;
      }
    },
  },
};
</script>

<style scoped>
</style>
