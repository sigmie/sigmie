<template>
  <li
    v-bind:class="[active ? 'bg-blue-400 rounded-full pr-20 pl-5 -ml-5 md:-mr-8 lg:-mr-12 m-0 mb-4 last:mb-0' : '']"
    class="py-3"
  >
    <a href class="text-red-800 block leading-tight">
      <div class="inline-flex h-4">
        <div class="flex-1 pr-4">
          <component width="20px" height="1rem"  :is="'icon-'+icon"/>
        </div>
        <div class="flex-1 leading-tight text-base">
          <span class="inline-block text-gray-100 font-semibold cursor-pointer">{{ text }}</span>
        </div>
      </div>
    </a>
  </li>
</template>

<script>
export default {
  props: ["text", "href", "icon", "active"]
};
</script>
