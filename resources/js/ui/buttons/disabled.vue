<template>
  <button
    type="submit"
    class="w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-gray-700 hover:bg-gray-500 focus:outline-none focus:bg-gray-700 focus:shadow-outline-indigo active:bg-gray-700 transition duration-150 ease-in-out opacity-50 cursor-not-allowed"
  >{{ value }}</button>
</template>

<script>
export default {
  props: ["value"]
};
</script>

<style>
</style>
