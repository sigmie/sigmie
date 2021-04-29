<template>
  <app>
    <form @submit.prevent="store" :action="$route('indices.index.store')">
      <form-input
        v-model="form.name"
        label="Index Name"
        id="name"
        type="text"
        :errors="form.errors.name"
      >
      </form-input>
    </form>
  </app>
</template>

<script>
import App from "../layouts/app";

export default {
  props: ["indices"],
  components: {
    App,
  },
  watch: {},
  methods: {
    create() {},
    store() {
      const route = this.$route("indices.index.store", {
        project: this.$page.props.project_id,
      });
      this.form.post(route);
    },
  },
  mounted() {},
  data() {
    return {
      form: this.$inertia.form({
        name: "foo",
      }),
    };
  },
};
</script>

<style scoped>
</style>
