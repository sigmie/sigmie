<template>
  <!-- This example requires Tailwind CSS v2.0+ -->
  <div class="flex flex-col bg-white shadow overflow-hidden rounded-lg">
    <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
      <h3 class="text-lg leading-6 font-medium text-gray-900">Receipts</h3>
    </div>
    <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
      <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
        <div
          class="shadow overflow-hidden border-b border-gray-200 sm:rounded-lg"
        >
          <table class="min-w-full divide-y divide-gray-200">
            <tbody>
              <tr
                v-for="(receipt, index) in receipts"
                :key="index"
                class="bg-white"
              >
                <td
                  class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-500"
                >
                  {{ toHumanDate(receipt.paid_at) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ receipt.amount }} $
                </td>
                <td
                  class="px-6 py-4 underline whitespace-nowrap text-sm text-gray-500"
                >
                  <a target="_blank" :href="receipt.receipt_url">
                    View receipt
                  </a>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import moment from "moment";

export default {
  props: ["receipts"],
  methods: {
    toHumanDate(datetime) {
      return moment.utc(datetime).format("LL");
    },
  },
};
</script>

<style scoped>
</style>
