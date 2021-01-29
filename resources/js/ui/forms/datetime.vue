<template>
  <div v-on-clickaway="() => (showDatepicker = false)">
    <label
      v-if="label.length > 0"
      :for="name"
      class="block text-sm font-medium leading-5 text-gray-700 pb-1"
      >{{ label }}</label
    >
    <div class="relative rounded-md shadow-sm mt-2">
      <input type="hidden" name="date" ref="date" />
      <input
        :name="name"
        type="text"
        readonly
        v-model="datepickerValue"
        @click="showDatepicker = !showDatepicker"
        @keydown.escape="showDatepicker = false"
        class="flex flex-1 appearance-none w-full px-3 py-2 border border-gray-300 focus:text-gray-700 rounded-md placeholder-gray-400 focus:outline-none focus:border-blue-300 transition duration-150 ease-in-out sm:text-sm sm:leading-5"
        placeholder="Select date"
      />

      <div class="absolute top-0 right-0 px-3 py-2">
        <svg
          class="h-6 w-6 text-gray-400"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
          />
        </svg>
      </div>
      <div
        class="bg-white mt-12 rounded-lg shadow-md p-4 absolute top-0 left-0"
        style="width: 17rem"
        v-if="showDatepicker"
      >
        <div class="flex justify-between items-center mb-2">
          <div>
            <span class="text-lg font-bold text-gray-800">{{
              MONTH_NAMES[month]
            }}</span>
            <span class="ml-1 text-lg text-gray-600 font-normal">{{
              year
            }}</span>
          </div>
          <div>
            <button
              type="button"
              class="transition ease-in-out duration-100 inline-flex cursor-pointer hover:bg-gray-200 p-1 rounded-full"
              :class="{ 'cursor-not-allowed opacity-25': month == 0 }"
              :disabled="month == 0 ? true : false"
              @click="() => month-- && getNoOfDays()"
            >
              <svg
                class="h-6 w-6 text-gray-500 inline-flex"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M15 19l-7-7 7-7"
                />
              </svg>
            </button>
            <button
              type="button"
              class="transition ease-in-out duration-100 inline-flex cursor-pointer hover:bg-gray-200 p-1 rounded-full"
              :class="{ 'cursor-not-allowed opacity-25': month == 11 }"
              :disabled="month == 11 ? true : false"
              @click="() => month++ && getNoOfDays()"
            >
              <svg
                class="h-6 w-6 text-gray-500 inline-flex"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M9 5l7 7-7 7"
                />
              </svg>
            </button>
          </div>
        </div>

        <div class="flex flex-wrap mb-3 -mx-1">
          <div
            v-for="(day, index) in DAYS"
            :key="index"
            style="width: 14.26%"
            class="px-1 text-gray-800 font-medium text-center text-xs"
          >
            {{ day }}
          </div>
        </div>

        <div class="flex flex-wrap -mx-1">
          <div
            v-for="blankday in blankdays"
            style="width: 14.26%"
            class="px-1 text-gray-800 font-medium text-center text-xs"
          ></div>

          <div
            v-for="(date, dateIndex) in no_of_days"
            :key="dateIndex"
            @click="() => getDateValue(date)"
            style="width: 14.26%"
            class="px-1 p-1 text-gray-800 font-medium text-center text-xs cursor-pointer rounded-full leading-loose transition ease-in-out duration-100"
            :class="{
              'bg-blue-200 text-white': isToday(date) == true,
              'text-gray-700 hover:bg-blue-200': isToday(date) == false,
            }"
          >
            {{ date }}
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
const MONTH_NAMES = [
  "January",
  "February",
  "March",
  "April",
  "May",
  "June",
  "July",
  "August",
  "September",
  "October",
  "November",
  "December",
];
const DAYS = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];

export default {
  props: ["name","label"],
  mounted() {
    this.initDate();
    this.getNoOfDays();
  },
  created() {
    this.MONTH_NAMES = MONTH_NAMES;
    this.DAYS = DAYS;
  },
  data() {
    return {
      showDatepicker: false,
      datepickerValue: "",
      month: "",
      year: "",
      no_of_days: [],
      blankdays: [],
      days: ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"],
    };
  },
  methods: {
    initDate() {
      let today = new Date();
      this.month = today.getMonth();
      this.year = today.getFullYear();
      this.datepickerValue = new Date(
        this.year,
        this.month,
        today.getDate()
      ).toDateString();
    },
    isToday(date) {
      const today = new Date();
      const d = new Date(this.year, this.month, date);

      return today.toDateString() === d.toDateString() ? true : false;
    },

    getDateValue(date) {
      let selectedDate = new Date(this.year, this.month, date);
      this.datepickerValue = selectedDate.toDateString();

      this.$refs.date.value =
        selectedDate.getFullYear() +
        "-" +
        ("0" + selectedDate.getMonth()).slice(-2) +
        "-" +
        ("0" + selectedDate.getDate()).slice(-2);

      console.log(this.$refs.date.value);

      this.showDatepicker = false;
    },

    getNoOfDays() {
      let daysInMonth = new Date(this.year, this.month + 1, 0).getDate();

      // find where to start calendar day of week
      let dayOfWeek = new Date(this.year, this.month).getDay();
      let blankdaysArray = [];
      for (var i = 1; i <= dayOfWeek; i++) {
        blankdaysArray.push(i);
      }

      let daysArray = [];
      for (var i = 1; i <= daysInMonth; i++) {
        daysArray.push(i);
      }

      this.blankdays = blankdaysArray;
      this.no_of_days = daysArray;
    },
  },
};
</script>
