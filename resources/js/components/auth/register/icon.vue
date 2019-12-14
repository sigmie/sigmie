<template>
  <svg
    :width="width"
    :height="height"
    viewBox="0 0 200 200"
    version="1.1"
    xmlns="http://www.w3.org/2000/svg"
    xmlns:xlink="http://www.w3.org/1999/xlink"
  >
    <defs>
      <rect id="path-1" x="681" y="49" width="478" height="830" rx="10" />
      <filter
        x="-2.6%"
        y="-1.4%"
        width="105.2%"
        height="103.0%"
        filterUnits="objectBoundingBox"
        id="filter-2"
      >
        <feOffset dx="0" dy="3" in="SourceAlpha" result="shadowOffsetOuter1" />
        <feGaussianBlur stdDeviation="2" in="shadowOffsetOuter1" result="shadowBlurOuter1" />
        <feColorMatrix
          values="0 0 0 0 0   0 0 0 0 0   0 0 0 0 0  0 0 0 0.141176471 0"
          type="matrix"
          in="shadowBlurOuter1"
          result="shadowMatrixOuter1"
        />
        <feMorphology radius="1" operator="erode" in="SourceAlpha" result="shadowSpreadOuter2" />
        <feOffset dx="0" dy="3" in="shadowSpreadOuter2" result="shadowOffsetOuter2" />
        <feGaussianBlur stdDeviation="1.5" in="shadowOffsetOuter2" result="shadowBlurOuter2" />
        <feColorMatrix
          values="0 0 0 0 0   0 0 0 0 0   0 0 0 0 0  0 0 0 0.121568627 0"
          type="matrix"
          in="shadowBlurOuter2"
          result="shadowMatrixOuter2"
        />
        <feOffset dx="0" dy="1" in="SourceAlpha" result="shadowOffsetOuter3" />
        <feGaussianBlur stdDeviation="4" in="shadowOffsetOuter3" result="shadowBlurOuter3" />
        <feColorMatrix
          values="0 0 0 0 0   0 0 0 0 0   0 0 0 0 0  0 0 0 0.2 0"
          type="matrix"
          in="shadowBlurOuter3"
          result="shadowMatrixOuter3"
        />
        <feMerge>
          <feMergeNode in="shadowMatrixOuter1" />
          <feMergeNode in="shadowMatrixOuter2" />
          <feMergeNode in="shadowMatrixOuter3" />
        </feMerge>
      </filter>
    </defs>
    <g id="Register" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
      <g id="Desktop-HD" transform="translate(-824.000000, -142.000000)">
        <polygon id="Rectangle" fill="#F6FAFC" points="0 0 1440 0 1440 1024 0 1024" />
        <g id="Rectangle">
          <use fill="black" fill-opacity="1" filter="url(#filter-2)" xlink:href="#path-1" />
          <use fill="#FFFFFF" fill-rule="evenodd" xlink:href="#path-1" />
        </g>
        <g id="Icon" transform="translate(824.000000, 142.000000)">
          <circle id="Oval" fill="#EDF2F7" cx="100" cy="100" r="100" />
          <polygon
            id="Path"
            fill="#BEE3F8"
            fill-rule="nonzero"
            points="152 129.597068 151.988779 129.602849 102.795863 157 48.9282709 130.061288 48 129.597068 50.7956934 73.6729938 99.4408273 39 99.9777283 39.3356669 150.322652 70.8767901"
          />
          <polygon
            id="Path"
            fill="#000000"
            fill-rule="nonzero"
            opacity="0.3"
            points="152 129.783355 102.835966 157 49 130.238774 96.6888478 97"
          />
          <polygon
            id="Path"
            fill="#000000"
            fill-rule="nonzero"
            opacity="0.1"
            points="152 129 97 96.1005554 100.31123 39 150.333405 70.4497788"
          />
          <circle id="Oval" fill="#3F3D56" fill-rule="nonzero" cx="162" cy="20" r="19" />
        </g>
      </g>
    </g>
  </svg>
</template>

<script>
export default {
  props: {
    height: {
      default: "200px"
    },
    width: {
      default: "200px"
    }
  }
};
</script>
