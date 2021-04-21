<template>
  <app>
    <div class="bg-white overflow-hidden shadow rounded-lg">
      <div class="px-4 py-5 sm:p-6">
        Analytics
        <canvas id="analytics" width="400" height="400"></canvas>
      </div>
    </div>
  </app>
</template>

<script>
import App from "../layouts/app";
import { Chart, registerables } from "chart.js";

Chart.defaults.font.size = 16;
Chart.register(...registerables);

export default {
  components: {
    App,
  },
  mounted() {
    var ctx = document.getElementById("analytics");
    ctx.height = 100;

    const labels = ["January", "February", "March", "April", "May", "June"];
    const data = {
      labels: labels,
      datasets: [
        {
          label: "My First dataset",
          backgroundColor: "rgb(255, 99, 132)",
          borderColor: "rgb(255, 99, 132)",
          data: [0, 10, 5, 2, 20, 30, 45],
        },
      ],
    };

    const config = {
      type: "line",
      data,
      options: {
        responsive: true,
        plugins: {
          title: {
            display: true,
            text: "Grid Line Settings",
          },
        },
        scales: {
          x: {
            grid: {
              display: true,
              drawBorder: true,
              drawOnChartArea: false,
              drawTicks: false,
            },
          },
          y: {
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
