<template>
  <svg
    class="inline-block"
    :width="width"
    :height="height"
    viewBox="0 0 15 11"
    version="1.1"
    xmlns="http://www.w3.org/2000/svg"
    xmlns:xlink="http://www.w3.org/1999/xlink"
  >
    <g id="Icons" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
      <g
        id="icon-announcement"
        transform="translate(-646.000000, -222.000000)"
        fill="#68d391"
        stroke="#FFFFFF"
      >
      </g>
      <g id="icon-check-circle" fill="#68d391" fill-rule="nonzero">
        <path
          d="M3.12771421,4.15804898 L5.59900618,6.54615989 L11.8722858,0.43703895 C12.6310597,-0.187137815 13.7553684,-0.13724687 14.4527064,0.551544533 C15.1500444,1.24033594 15.1853239,2.3358213 14.5336771,3.0658122 L6.92970185,10.4708073 C6.19051274,11.1763976 5.00749961,11.1763976 4.2683105,10.4708073 L0.466322853,6.76830975 C-0.185323948,6.03831884 -0.150044372,4.94283348 0.547293628,4.25404208 C1.24463163,3.56525067 2.36894031,3.51535973 3.12771421,4.13953649 L3.12771421,4.15804898 Z"
          id="Shape"
        />
      </g>
    </g>
  </svg>
</template>

<script>
export default {
  props: {
    height: {
      default: "20px"
    },
    width: {
      default: "20px"
    }
  }
};
</script>
