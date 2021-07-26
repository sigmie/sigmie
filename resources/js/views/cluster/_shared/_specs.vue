<template>
  <div>
    <div class="flex flex-col-reverse lg:grid lg:grid-cols-3 gap-6">
      <div class="flex-1 md:col-span-2 sm:px-0">
        <div class="shadow sm:rounded-md sm:overflow-hidden">
          <div class="px-4 py-5 bg-white sm:p-6 rounded-md">
            <div class="grid grid-cols-4 gap-6">
              <div class="col-span-4 sm:col-span-4">
                <legend class="text-base leading-6 font-medium text-gray-900">
                  Cluster details
                </legend>
                <p class="text-sm leading-5 text-gray-500">
                  Pick you Cluster specification and it's location.
                </p>
              </div>

              <div class="col-span-2 sm:col-span-2">
                <form-select
                  label="Datacenter location"
                  name="data_center"
                  @change="dataCenterChange"
                  id="data-center"
                  v-model.trim="$v.dataCenter.$model"
                  aria-label="Data center"
                  :items="regions"
                  :validations="$v.dataCenter"
                ></form-select>
              </div>
              <div class="col-span-2 sm:col-span-2">
                <form-slider
                  :min="1"
                  :max="3"
                  :value="nodes"
                  @change="nodesChange"
                  label="Nodes"
                ></form-slider>
              </div>

              <div class="col-span-2 sm:col-span-2">
                <form-slider
                  :min="1"
                  :max="2"
                  :value="cores"
                  :valueFormat="formatCores"
                  @change="coresChanged"
                  label="Cores"
                ></form-slider>
              </div>
              <div class="col-span-2 sm:col-span-2">
                <form-slider
                  :value="memory"
                  :min="minMemory"
                  :max="maxMemory"
                  @change="memoryChanged"
                  :valueFormat="formatMemory"
                  label="Memory"
                ></form-slider>
              </div>

              <div class="col-span-2 sm:col-span-2">
                <form-input
                  suffix="GB"
                  type="number"
                  :value="disk"
                  label="Disk size"
                  class="max-w-sm"
                  @change="(value) => set('disk', parseInt(value))"
                  :error-messages="errorMessages.disk"
                  :validations="$v.disk"
                  id="disk"
                  name="disk"
                ></form-input>
              </div>

              <div class="col-span-2 sm:col-span-2">
                <form-select
                  v-model.trim="$v.version.$model"
                  :validations="$v.version"
                  @change="versionChange"
                  label="Version"
                  name="version"
                  id="version"
                  aria-label="Elasticsearch version"
                  :items="{
                    '7.3': { id: 1, name: '7.3' },
                  }"
                ></form-select>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="flex-1 md:col-span-1 pt-4 sm:pt-0">
        <div class="pb-2 md:pb-4 lg:pb-0 lg:px-4">
          <h3 class="text-lg font-medium leading-6 text-gray-900">
            Cluster details
          </h3>
          <p class="mt-1 text-sm leading-5 text-gray-600">
            Choose where you want your data to be stored, the desired number of
            Elasticsearch instances and their specifications.
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
const minMemory = (cores) => {
  let memoryPerCore = cores * 1000;

  if (memoryPerCore > 2048) {
    return memoryPerCore;
  }

  return 2048;
};
const maxMemory = (cores) => 6500 * cores;

import {
  required,
  minValue,
  maxValue,
  maxLength,
  minLength,
} from "vuelidate/lib/validators";

import ceil from "lodash/ceil";
import round from "lodash/round";
import floor from "lodash/floor";
export default {
  props: ["regions"],
  validations: {
    version: {
      required,
    },
    dataCenter: {
      required,
    },
    nodes: {
      required,
      minValue: minValue(1),
      maxValue: maxValue(3),
    },
    disk: {
      required,
      minValue: minValue(10),
      maxValue: maxValue(30),
    },
  },
  data() {
    return {
      dataCenter: null,
      nodes: 3,
      cores: 1,
      version: null,
      disk: 10,
      direction: "decrease",
      memory: 2048,
      memoryPerCore: 1024,
      minMemory: minMemory(1),
      maxMemory: maxMemory(1),
      errorMessages: {
        disk: {
          minValue: "The minimum disk size is 10 GB",
          maxValue: "The maximum disk size is 30 GB",
        },
      },
    };
  },
  watch: {
    cores(newCores, oldCores) {
      let min = minMemory(newCores);
      let max = maxMemory(newCores);

      if (this.memory > max) {
        max = floor(max / 256) * 256;
        this.memory = max;
        this.$emit("memoryChange", this.memory);

        this.minMemory = min;
        this.maxMemory = max;
      } else if (this.memory < min) {
        min = ceil(min / 256) * 256;
        this.memory = min;
        this.$emit("memoryChange", this.memory);

        this.minMemory = min;
        this.maxMemory = max;
      } else {
        this.minMemory = min;
        this.maxMemory = max;
      }
    },
    "$v.$invalid": function (value) {
      this.$emit("validate", value);
    },
  },
  methods: {
    nodesChange(value) {
      this.nodes = value;
      this.$emit("nodesChange", value);
    },
    dataCenterChange(value) {
      this.dataCenter = value;
      this.$emit("dataCenterChange", value);
    },
    versionChange(value) {
      this.version = value;
      this.$emit("versionChange", value.name);
    },
    coresChanged(value) {
      if (value % 2 !== 0 && value !== 1) {
        value = value + 1;
      }

      this.cores = value;

      this.$emit("coresChange", value);
    },
    formatMemory(memory) {
      // To Mbibyt
      memory = memory / 1000;

      if (this.direction === "increase") {
        memory = ceil(memory / 0.25) * 0.25;
      } else if (this.direction === "decrease") {
        memory = floor(memory / 0.25) * 0.25;
      }

      this.$emit("memoryInGBWasUpdated", memory);

      return memory + " GB";
    },
    formatCores(cores) {
      return cores + " vCPU";
    },
    memoryChanged(value) {
      if (value > this.memory) {
        this.direction = "increase";
        value = floor(value / 256) * 256;
      } else if (value < this.memory) {
        this.direction = "decrease";
        value = ceil(value / 256) * 256;
      }

      this.memory = value;

      this.$emit("memoryChange", value);
    },
    set(key, value) {
      this[key] = value;
      this.$v[key].$touch();

      this.$emit(`${key}Change`, value);
    },
  },
};
</script>

<style scoped>
</style>
