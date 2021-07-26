<template>
  <div class="mt-2 flex-row lg:flex lg:space-x-6">
    <!-- This example requires Tailwind CSS v2.0+ -->
    <div class="w-full">
      <h2 class="text-gray-500 text-xs font-medium uppercase tracking-wide">
        Plans
      </h2>
      <ul
        class="mt-3 grid grid-cols-1 gap-5 sm:gap-6 sm:grid-cols-2 lg:grid-cols-3 w-full"
      >
        <new-plan
          @createRequest="() => $emit('createRequest')"
          class="col-span-1 flex shadow-sm rounded-md bg-white"
        ></new-plan>

        <plan
          @deleteRequest="deleteRequest"
          @editRequest="(plan) => $emit('editRequest', plan)"
          @triggerRequest="triggerAction"
          :plan="plan"
          v-for="(plan, index) in plans"
          :key="index"
        ></plan>
        <modal
          title="Delete"
          content="Are you sure you want to permanently delete this plan and all it's activities ?"
          primaryText="Delete"
          secondaryText="Cancel"
          @primaryAction="deleteAction"
          @secondaryAction="state = 'NONE'"
          @clickAway="state = 'NONE'"
          @onEsc="state = 'NONE'"
          :icon="true"
          :show="state === STATE_DELETE_REQUEST"
          type="danger"
        ></modal>
      </ul>
    </div>
  </div>
</template>

<script>
import Plan from "./_plans/_plan.vue";
import NewPlan from "./_plans/_new-plan.vue";

const STATE_DELETE_REQUEST = "DELETE_REQUEST";
const STATE_TRIGGER_REQUEST = "STATE_TRIGGER_REQUEST";
const STATE_NONE = "NONE";

export default {
  methods: {
    deleteRequest(id) {
      this.state = STATE_DELETE_REQUEST;
      this.planId = id;
    },
    deleteAction() {
      let route = this.$route("indexing.plan.destroy", { plan: this.planId });
      this.$inertia.delete(route, {
        preserveState: false,
        preserveScroll: false,
        only: ["plans", "activities"],
      });
    },
    triggerAction(id) {
      this.state = STATE_TRIGGER_REQUEST;
      let route = this.$route("indexing.plan.trigger", { plan: id });
      this.$inertia.post(route, {
        preserveState: false,
        preserveScroll: false,
        only: ["plans", "activities"],
      });
    },
  },
  created() {
    this.STATE_DELETE_REQUEST = STATE_DELETE_REQUEST;
    this.STATE_NONE = STATE_NONE;
    this.STATE_TRIGGER_REQUEST = STATE_TRIGGER_REQUEST;
  },
  components: {
    Plan,
    NewPlan,
  },
  props: ["plans"],
  data() {
    return {
      state: STATE_NONE,
      planId: null,
    };
  },
};
</script>

<style scoped>
</style>
