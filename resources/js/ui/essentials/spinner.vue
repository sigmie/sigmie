<template>
  <div v-if="show" class="modal-mask">
    <div class="modal-wrapper pl-64">
      <div class="modal-container">
        <div class="trinity-rings-spinner">
          <div class="circle"></div>
          <div class="circle"></div>
          <div class="circle"></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  props:{
      show: {
          default: false
      }
  }
};
</script>

<style>
.trinity-rings-spinner,
.trinity-rings-spinner * {
  margin: auto;
  box-sizing: border-box;
}

.modal-container {
  text-align: center;
  margin: 0px auto;
  padding: 20px 30px;
  border-radius: 2px;
  transition: all 0.3s ease;
  font-family: Helvetica, Arial, sans-serif;
}

.modal-mask {
  position: fixed;
  z-index: 9998;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.5);
  display: table;
  transition: opacity 0.3s ease;
}

.modal-wrapper {
  display: table-cell;
  vertical-align: middle;
}

.trinity-rings-spinner {
  height: 66px;
  width: 66px;
  padding: 3px;
  position: relative;
  display: flex;
  justify-content: center;
  align-items: center;
  flex-direction: row;
  overflow: hidden;
  box-sizing: border-box;
}

.trinity-rings-spinner .circle {
  position: absolute;
  display: block;
  border-radius: 50%;
  border: 3px solid #313758;
  opacity: 1;
}

.trinity-rings-spinner .circle:nth-child(1) {
  height: 60px;
  width: 60px;
  animation: trinity-rings-spinner-circle1-animation 1.5s infinite linear;
  border-width: 3px;
}
.trinity-rings-spinner .circle:nth-child(2) {
  height: calc(60px * 0.65);
  width: calc(60px * 0.65);
  animation: trinity-rings-spinner-circle2-animation 1.5s infinite linear;
  border-width: 2px;
}
.trinity-rings-spinner .circle:nth-child(3) {
  height: calc(60px * 0.1);
  width: calc(60px * 0.1);
  animation: trinity-rings-spinner-circle3-animation 1.5s infinite linear;
  border-width: 1px;
}

@keyframes trinity-rings-spinner-circle1-animation {
  0% {
    transform: rotateZ(20deg) rotateY(0deg);
  }
  100% {
    transform: rotateZ(100deg) rotateY(360deg);
  }
}
@keyframes trinity-rings-spinner-circle2-animation {
  0% {
    transform: rotateZ(100deg) rotateX(0deg);
  }
  100% {
    transform: rotateZ(0deg) rotateX(360deg);
  }
}
@keyframes trinity-rings-spinner-circle3-animation {
  0% {
    transform: rotateZ(100deg) rotateX(-360deg);
  }
  100% {
    transform: rotateZ(-360deg) rotateX(360deg);
  }
}
</style>
