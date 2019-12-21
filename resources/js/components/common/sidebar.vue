<template>
  <div class="m-0 h-full px-3 py-3">
    <div class="col-md-12 align-middle mb-8">
      <logo-white />
    </div>

    <div class="col-md-12 flex flex-col justify-center">
      <div class="py-2 leading-relaxed">
        <divider-sidebar text="Basics" />
        <ul class="pl-3">
          <list-sidebar text="Nodes" :active="true" icon="server" />
          <list-sidebar text="Notifications" :active="false" icon="notification" />
          <list-sidebar text="Actions" :active="false" icon="refresh" />
        </ul>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  props: ["active"]
};
</script>


