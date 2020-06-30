 <template>
  <div
    class="z-50 fixed bottom-0 inset-x-0 px-4 pb-4 sm:inset-0 sm:flex sm:items-center sm:justify-center"
    :class="show ? 'ease-out duration-300 opacity-100 visible' : 'ease-in duration-200 opacity-0 invisible'"
  >
    <div @click="$emit('clickAway')" class="fixed inset-0 transition-opacity">
      <div class="absolute inset-0 bg-black opacity-75"></div>
    </div>

    <div
      :class="show ?'ease-out duration-300 opacity-100 translate-y-0 sm:scale-100':'ease-in duration-200 opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95' "
      class="bg-white rounded-lg pb-4 overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full p-6"
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
        <div
          v-if="icon"
          class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10"
          :class="type === 'danger' ? 'bg-red-100' : type === 'success' ? 'bg-green-100': type === 'warning' ? 'bg-yellow-100' : type === 'info' ? 'bg-blue-100': ''"
        >
          <icon-danger v-if="type === 'danger'" class="h-6 w-6 text-red-600"></icon-danger>
          <icon-danger v-if="type === 'warning'" class="h-6 w-6 text-yellow-500"></icon-danger>
          <icon-info v-if="type === 'info'" class="h-6 w-6 text-blue-500"></icon-info>
          <icon-check v-if="type === 'success'" class="h-6 w-6 text-green-500"></icon-check>
        </div>
        <div class="mt-3 sm:mt-0 sm:ml-4 sm:text-left">
          <h3 class="text-lg leading-6 font-medium text-gray-900">{{ title }}</h3>
          <div class="mt-2 text-sm leading-5 text-gray-500">
            <p v-if="content">{{ content }}</p>
            <slot></slot>
          </div>
        </div>
      </div>
      <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
        <span v-if="primaryText" class="flex w-full rounded-md shadow-sm sm:ml-3 sm:w-auto">
          <button
            @click="()=>$emit('primaryAction')"
            type="button"
            class="inline-flex justify-center w-full rounded-md border border-transparent px-4 py-2 text-base leading-6 font-medium shadow-sm focus:outline-none transition ease-in-out duration-150 sm:text-sm sm:leading-5"
            :class="type === 'danger' ? 'bg-red-500  focus:shadow-outline-red focus:border-red-700 hover:bg-red-400 text-white' : type === 'success' ? 'bg-green-500 focus:shadow-outline-green focus:border-green-700 hover:bg-green-400 text-white': type === 'warning' ? 'bg-yellow-400 hover:bg-yellow-500 focus:border-yellow-700 focus:shadow-outline-yellow text-white' : type === 'info' ? 'bg-blue-500 focus:border-blue-700 hover:bg-blue-600 focus:shadow-outline-blue text-white': ''"
          >{{ primaryText }}</button>
        </span>
        <span v-if="secondaryText" class="mt-3 flex w-full rounded-md shadow-sm sm:mt-0 sm:w-auto">
          <button
            @click="()=>$emit('secondaryAction')"
            type="button"
            class="inline-flex justify-center w-full rounded-md border border-gray-300 px-4 py-2 bg-white text-base leading-6 font-medium text-gray-700 shadow-sm hover:text-gray-500 focus:outline-none focus:border-blue-300 focus:shadow-outline-blue transition ease-in-out duration-150 sm:text-sm sm:leading-5"
          >{{ secondaryText }}</button>
        </span>
      </div>
    </div>
  </div>
</template>

<script>
import { mixin as clickaway } from "vue-clickaway";

export default {
  mixins: [clickaway],
  updated() {
    this.$nextTick(function() {
      this.$refs.modal.focus();
    });
  },
  props: [
    "show",
    "type",
    "title",
    "content",
    "actions",
    "icon",
    "primaryText",
    "secondaryText"
  ],
  mounted() {
    if (this.show === true) {
      this.$refs.modal.focus();
    }
  }
};
</script>
