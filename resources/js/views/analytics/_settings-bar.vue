<template>
  <div class="bg-white rounded-lg">
    <div class="max-w-7xl mx-auto px-4">
      <div class="relative h-16 flex items-center justify-between">
        <div class="flex items-center">
          <!-- Links section -->
          <div class="block">
            <div class="flex space-x-4">
              <!-- Current: "bg-gray-100", Default: "hover:text-gray-700" -->
              <a
                href="#"
                class="bg-gray-50 px-3 py-2 rounded-md text-sm font-medium text-gray-900"
                aria-current="page"
                >Producs</a
              >

              <a
                href="#"
                class="hover:text-gray-700 px-3 py-2 rounded-md text-sm font-medium text-gray-900"
                >Users</a
              >

              <a
                href="#"
                class="hover:text-gray-700 px-3 py-2 rounded-md text-sm font-medium text-gray-900"
                >Mails</a
              >

              <a
                href="#"
                class="hover:text-gray-700 px-3 py-2 rounded-md text-sm font-medium text-gray-900"
                >Users</a
              >
              <a
                href="#"
                class="hover:text-gray-700 flex font-medium text-gray-900"
              >
                <icon-cheveron-down
                  class="w-4 h-4 self-center"
                ></icon-cheveron-down>
              </a>
            </div>
          </div>
        </div>

        <div class="flex space-x-4">
          <form-datetime label=""></form-datetime>
          <div class="flex font-medium text-gray-400">
            <span class="self-center"> - </span>
          </div>
          <form-datetime label=""></form-datetime>
          <button
            href="#"
            class="flex font-medium text-gray-400 hover:text-gray-500"
          >
            <icon-cheveron-down
              class="w-5 h-5 self-center"
            ></icon-cheveron-down>
          </button>
          <button
            class="flex-shrink-0 self-center border-l px-4 text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-50 focus:ring-purple-500"
          >
            <icon-filter class="h-5 w-5"></icon-filter>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {};
</script>

<style scoped>
</style>


