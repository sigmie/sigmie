<template>
  <app>
    <div class="bg-white overflow-hidden shadow rounded-lg">
      <div class="px-4 py-5">
        <canvas id="analytics" width="400" height="400"></canvas>
      </div>
    </div>
  </app>
</template>

<script>
import App from "../layouts/app";
import { Chart, registerables } from "chart.js";
import moment from "moment";

Chart.defaults.font.size = 16;
Chart.register(...registerables);

export default {
  components: {
    App,
  },
  mounted() {
    var ctx = document.getElementById("analytics");
    ctx.height = 100;

    const data = {
      labels: [
        "Aug 2019",
        "Sep 2019",
        "Oct 2019",
        "Nov 2019",
        "Dec 2019",
        "Jan 2020",
      ],
      datasets: [
        {
          label: "My First dataset",
          fill: true,
          backgroundColor: "rgba(101, 116, 205, 0.1)",
          borderColor: "rgba(101, 116, 205, 0.8)",
          borderWidth: 2,
          data: [
            0,
            10,
            12,
            35,
            53,
            45,
            45,
            23,
            2,
            34,
            4,
            5,
            7,
            45,
            45,
            23,
            45,
            45,
            23,
            8,
            5,
            2,
            20,
            30,
          ],
          tension: 0.3,
        },
      ],
    };

    const config = {
      type: "line",
      data,
      options: {
        interaction: {
          intersect: false,
          mode: "nearest",
        },
        layout: {
          //   padding: 20,
          //   padding: {
          //     left: 50,
          //   },
        },
        elements: {
          point: {
            radius: 1,
          },
        },
        responsive: true,
        plugins: {
          title: {
            display: false,
            text: "Grid Line Settings",
          },
          legend: {
            display: false,
          },
        },
        scales: {
          x: {
            ticks: {
              display: true,
              padding: 15,
              stepSize: 2,
            },
            grid: {
              display: true,
              drawBorder: true,
              drawOnChartArea: false,
              drawTicks: false,
            },
          },
          y: {
            ticks: {
              display: true, // Hide the left side labels
              padding: 15,
              stepSize: 10,
            },
            grid: {
              display: true,
              drawBorder: true,
              drawOnChartArea: false,
              drawTicks: false,
            },
          },
        },
      },
    };

    const chart = new Chart(ctx, config);
  },
};
</script>

<style scoped>
</style>
