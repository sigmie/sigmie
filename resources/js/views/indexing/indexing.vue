<template>
  <app>
    <div class="mt-8">
      <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <plans
          @createRequest="() => (showCreateForm = true)"
          @editRequest="editRequest"
          :plans="plans"
        ></plans>
      </div>
    </div>

    <h2
      class="max-w-6xl mx-auto mt-8 mb-4 px-4 text-lg leading-6 font-medium text-gray-900 sm:px-6 lg:px-8"
    >
      Recent activity
    </h2>

    <activities :activities="activities"></activities>
    <create-form
      :clusterId="clusterId"
      @hide="showCreateForm = false"
      :showForm="showCreateForm"
    ></create-form>
    <update-form
      ref="updateForm"
      :clusterId="clusterId"
      @hide="showUpdateForm = false"
      :plan="planToUpdate"
      :showForm="showUpdateForm"
    ></update-form>
  </app>
</template>

<script>
import App from "../layouts/app";
import Plans from "./_plans";
import Activities from "./_activities";
import updateForm from "./_update-form";
import createForm from "./_create-form";

export default {
  components: {
    App,
    Plans,
    Activities,
    createForm,
    updateForm,
  },
  props: ["plans", "clusterId", "activities"],
  methods: {
    editRequest(plan) {
      this.showUpdateForm = true;
      this.$refs.updateForm.edit(plan)
    },
  },
  data() {
    return {
      planToUpdate: null,
      showCreateForm: false,
      showUpdateForm: false,
    };
  },
};
</script>

<style scoped>
</style>
