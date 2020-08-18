<template>
  <div class="mx-auto max-w-md">
    <heading class="mb-6">Feature Flags</heading>

    <card class="flex flex-col items-center justify-center">
      <table cellpadding="0" cellspacing="0" data-testid="resource-table" class="table w-full">
        <thead>
          <tr>
            <th class="text-left">
              <span class="cursor-pointer inline-flex items-center">Feature</span>
            </th>
            <th class="text-center">
              <span class="cursor-pointer inline-flex items-center">State</span>
            </th>
          </tr>
        </thead>
        <tbody v-for="(state, name) in features" :key="name">
          <tr dusk="id-row">
            <td>{{name}}</td>
            <td class="td-fit text-right pr-6">
              <span>
                <a
                  @click="()=>toogle(name, state)"
                  class="cursor-pointer text-70 hover:text-primary mr-3"
                  title="Toogle"
                >
                  <boolean-icon :value="state"></boolean-icon>
                </a>
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </card>
  </div>
</template>

<script>
export default {
  data() {
    return {
      features: [],
    };
  },
  mounted() {
    this.all();
  },
  methods: {
    async toogle(feature, state) {
      if (state === true) {
        await this.turnOff(feature);
      }

      if (state === false) {
        await this.turnOn(feature);
      }

      await this.all();
    },
    async all() {
      return axios
        .get("/sigmie/nova-feature-flags/all")
        .then((response) => {
          this.features = response.data;
        })
        .catch((error) => this.$toasted.show(error, { type: "error" }));
    },
    async turnOn(feature) {
      return axios
        .patch(`/sigmie/nova-feature-flags/on/${feature}`)
        .then((response) => null)
        .catch((error) => this.$toasted.show(error, { type: "error" }));
    },
    async turnOff(feature) {
      return axios
        .patch(`/sigmie/nova-feature-flags/off/${feature}`)
        .then((response) => null)
        .catch((error) => this.$toasted.show(error, { type: "error" }));
    },
  },
};
</script>