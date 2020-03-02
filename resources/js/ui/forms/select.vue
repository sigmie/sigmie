<template>
  <fieldset>
    <legend class="block text-sm font-medium leading-5 text-gray-700">{{ label }}</legend>
    <div class="mt-1 rounded-md shadow-sm">
      <div>
        <select
          :id="id"
          name="name"
          :aria-label="label"
          class="form-select relative block w-full rounded-none rounded-md bg-transparent focus:z-10 transition ease-in-out duration-150 sm:text-sm sm:leading-5"
        >
          <option v-for="(value, index) in values" :key="index">{{ value }}</option>
        </select>
      </div>
    </div>
  </fieldset>
</template>

<script>
export default {
  props: ["values", "aria-label", "label", "name", "id"]
};
</script>

<style>
</style>
