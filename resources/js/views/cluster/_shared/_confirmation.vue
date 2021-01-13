<template>
  <div
    class="z-50 fixed bottom-0 inset-x-0 px-4 w-80pb-4 inset-0 flex items-center justify-center"
    :class="
      show
        ? 'ease-out duration-300 opacity-100 visible'
        : 'ease-in duration-200 opacity-0 invisible'
    "
  >
    <div @click="$emit('clickAway')" class="fixed inset-0 transition-opacity">
      <div class="absolute inset-0 bg-black opacity-75"></div>
    </div>

    <div
      :class="[
        show
          ? 'ease-out duration-300 opacity-100 translate-y-0 sm:scale-100'
          : 'ease-in duration-200 opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95',
      ]"
      class="bg-white rounded-lg pb-4 overflow-hidden shadow-xl transform transition-all p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="modal-headline"
    >
      <div
        class="sm:flex sm:items-start focus:outline-none"
        tabindex="-1"
        ref="modal"
        @keyup.esc="$emit('onEsc')"
      >
        <div class="grid grid-cols-3 gap-y-2 mx-auto">
          <div class="col-span-3 pt-3">
            <icon-sigmie class="mx-auto w-16 text-gray-400"></icon-sigmie>
            <h3 class="text-center font-medium text-gray-900 text-lg pt-3">
              Elasticsearch
            </h3>
            <div class="text-gray-500 text-center text-xs font-medium mx-auto">
              version {{ cluster.version }}
            </div>
          </div>

          <div class="col-span-3">
            <div class="group relative rounded-lg cursor-pointer">
              <div
                class="rounded-lg bg-white px-3 py-4 sm:flex sm:justify-between sm:space-x-4"
              >
                <div class="flex items-center space-x-0 w-full">
                  <ul class="space-y-1 w-full">
                    <li
                      class="group relative bg-gray-50 hover:bg-gray-100 rounded-lg cursor-pointer focus:outline-none w-60"
                    >
                      <div
                        class="rounded-lg py-2 sm:flex sm:justify-between sm:space-x-4 px-1"
                      >
                        <div class="flex items-center space-x-0 px-2">
                          <div class="text-sm leading-5">
                            <div class="flex justify-center space-x-3">
                              <div clas="flex-1">
                                <icon-globe
                                  class="h-7 text-gray-400"
                                ></icon-globe>
                              </div>
                              <p
                                class="self-center text-xs font-medium text-gray-600"
                              >
                                {{ cluster.data_center }}
                              </p>
                            </div>
                          </div>
                        </div>
                      </div>
                    </li>
                    <li
                      class="group relative bg-gray-50 hover:bg-gray-100 w-full rounded-lg cursor-pointer focus:outline-none w-60"
                    >
                      <div
                        class="rounded-lg py-2 sm:flex sm:justify-between sm:space-x-4 px-1"
                      >
                        <div class="flex items-center space-x-0 px-2">
                          <div class="text-sm leading-5">
                            <div class="flex justify-center space-x-3">
                              <div clas="flex-1">
                                <icon-link
                                  class="h-7 text-base font-normal text-gray-400"
                                ></icon-link>
                              </div>
                              <p
                                class="self-center text-xs font-medium text-gray-600"
                              >
                                {{ cluster.url }}
                              </p>
                            </div>
                          </div>
                        </div>
                      </div>
                    </li>
                    <li
                      class="group relative rounded-lg cursor-pointer focus:outline-none w-60"
                    >
                      <div
                        class="rounded-lg bg-gray-50 hover:bg-gray-100 px-1 py-2 sm:flex sm:justify-between sm:space-x-4"
                      >
                        <div class="flex items-center space-x-0 px-2">
                          <div class="text-sm leading-5">
                            <div class="flex justify-center space-x-3">
                              <icon-server
                                class="h-7 self-center text-gray-400"
                              ></icon-server>
                              <div clas="flex-1">
                                <p
                                  class="block text-xs font-medium text-gray-600"
                                >
                                  Node x{{ cluster.nodes }}
                                </p>
                                <div class="text-gray-500 text-xs">
                                  <div class="block sm:inline">
                                    {{ cluster.memory }} GB /
                                    {{ cluster.cores }} CPUs /
                                    {{ cluster.disk }} GB SSD
                                  </div>
                                </div>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </div>

          <div class="col-span-3">
            <div class="grid grid-cols-2 gap-4 px-3">
              <div class="col-span-1">
                <span class="flex w-full rounded-md sm:ml-3 sm:w-auto">
                  <button
                    @click="() => $emit('cancel')"
                    type="button"
                    class="inline-flex justify-center w-full rounded-md border border-transparent px-4 py-2 text-base leading-6 font-medium focus:outline-none transition ease-in-out duration-150 sm:text-sm sm:leading-5 focus:ring-blue text-gray-800"
                  >
                    Cancel
                  </button>
                </span>
              </div>

              <div class="col-span-1">
                <span
                  class="flex w-full rounded-md shadow-sm sm:ml-3 sm:w-auto"
                >
                  <button
                    @click="() => $emit('confirm')"
                    type="button"
                    class="inline-flex justify-center w-full rounded-md border border-transparent px-4 py-2 text-base leading-6 font-medium shadow-sm focus:outline-none transition ease-in-out duration-150 sm:text-sm sm:leading-5 bg-blue-500 focus:border-blue-700 hover:bg-blue-600 focus:ring-blue text-white"
                  >
                    Create
                  </button>
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  props: ["show", "cluster"],
};
</script>

<style scoped>
</style>
