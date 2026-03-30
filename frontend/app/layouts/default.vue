<template>
  <div class="min-h-screen bg-app-bg">
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
      <div class="glow g1" />
      <div class="glow g2" />
      <div class="glow g3" />
    </div>

    <div class="grid-overlay fixed inset-0 pointer-events-none z-0" />
    <div class="relative z-10">
      <slot />
    </div>

    <AppToast />
  </div>
</template>

<style scoped>
.glow { position:absolute; border-radius:50%; filter:blur(90px); opacity:.18; animation:drift 14s ease-in-out infinite alternate; }
.g1 { width:500px;height:500px;background:radial-gradient(circle,#c8a96e,transparent 70%);top:-180px;left:-120px;animation-duration:16s; }
.g2 { width:380px;height:380px;background:radial-gradient(circle,#8b6c3a,transparent 70%);bottom:-100px;right:-80px;animation-duration:20s; }
.g3 { width:250px;height:250px;background:radial-gradient(circle,#c8a96e,transparent 70%);top:50%;left:60%;opacity:.08;animation-duration:12s; }
.grid-overlay {
  background-image:
    linear-gradient(rgba(200,169,110,0.03) 1px,transparent 1px),
    linear-gradient(90deg,rgba(200,169,110,0.03) 1px,transparent 1px);
  background-size:48px 48px;
}
@keyframes drift {
  from { transform:translate(0,0) scale(1); }
  to { transform:translate(30px,20px) scale(1.08); }
}
</style>