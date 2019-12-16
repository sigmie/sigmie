<template>
  <svg
    class="inline-block"
    :width="width"
    :height="height"
    viewBox="0 0 30 30"
    version="1.1"
    xmlns="http://www.w3.org/2000/svg"
    xmlns:xlink="http://www.w3.org/1999/xlink"
  >
    <g id="Symbols" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
      <g id="Check-text-list" transform="translate(0.000000, -5.000000)">
        <g id="Group-4" transform="translate(0.000000, 5.000000)">
          <g id="icon-check-circle" />
          <circle id="Oval" fill="#CBD5E0" cx="15" cy="15" r="15" />
          <path
            d="M9.17028561,13.6700668 L12.4653416,16.9265817 L20.8297144,8.5959622 C21.8414129,7.74481207 23.3404912,7.81284518 24.2702752,8.75210618 C25.2000592,9.69136719 25.2470986,11.1852109 24.3782362,12.180653 L14.2396025,22.2783736 C13.254017,23.2405421 11.6766661,23.2405421 10.6910807,22.2783736 L5.6217638,17.2295133 C4.7529014,16.2340711 4.79994084,14.7402275 5.72972484,13.8009665 C6.65950884,12.8617055 8.15858708,12.7936724 9.17028561,13.6448225 L9.17028561,13.6700668 Z"
            id="Path"
            fill="#FFFFFF"
            fill-rule="nonzero"
          />
        </g>
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
