<!-- app/components/AppTopbar.vue -->
<template>
  <header

    class="sticky top-0 z-20 h-14 flex items-center gap-3 px-3 sm:px-6 transition-colors"
    style="background: var(--topbar-bg); backdrop-filter: blur(12px);
           border-bottom: 1px solid var(--app-border-2);"
  >
    <!-- Hamburger — mobile only -->
    <button
      class="lg:hidden w-9 h-9 rounded-xl flex items-center justify-center shrink-0 nav-inactive transition-colors"
      style="background: var(--app-surface-2); border: 1px solid var(--app-border);"
      @click="toggleMobile"
    >
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
           stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
        <path d="M3 6h18M3 12h18M3 18h18"/>
      </svg>
    </button>

    <h1 class="font-serif text-[16px] sm:text-[17px] font-normal flex-1 truncate"
        style="color: var(--app-text)">
      {{ title }}
    </h1>

    <div class="flex items-center gap-1.5 sm:gap-2">

      <ThemeToggle />

      <NuxtLink
        :to="auth.isInternal ? '/admin/notifs' : '/client/notifs'"
        class="relative w-9 h-9 rounded-xl flex items-center justify-center transition-colors nav-inactive"
        style="background: var(--app-surface-2); border: 1px solid var(--app-border);"
      >
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
          <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
        </svg>
        <span
          v-if="notifs.unreadCount > 0"
          class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 rounded-full text-[10px] leading-[18px] text-center font-bold"
          style="background:#ef4444;color:#ffffff"
        >
          {{ notifs.unreadCount > 99 ? '99+' : notifs.unreadCount }}
        </span>
      </NuxtLink>

      <!-- Shows the uploaded photo when set, otherwise nom+prenom initials -->
      <div ref="avatarMenuRef" class="relative">
        <button
          type="button"
          class="w-10 h-10 rounded-xl flex items-center justify-center transition-colors nav-inactive"
          style="background: var(--app-surface-2); border: 1px solid var(--app-border);"
          aria-haspopup="menu"
          :aria-expanded="avatarMenuOpen"
          @click="avatarMenuOpen = !avatarMenuOpen"
        >
          <UserAvatar :size="36" font-size="12px" />
        </button>

        <div
          v-if="avatarMenuOpen"
          class="absolute right-0 mt-2 w-48 rounded-xl py-2 shadow-xl"
          style="background:var(--app-surface);border:1px solid var(--app-border);"
          role="menu"
        >
          <button class="menu-item" role="menuitem" @click="goToProfile">Mon Profil</button>
          <button class="menu-item" role="menuitem" @click="goToSettings">Paramètres</button>
          <div class="my-1" style="border-top:1px solid var(--app-border-2)" />
          <button class="menu-item danger" role="menuitem" @click="logout">Déconnexion</button>
        </div>
      </div>
    </div>
  </header>
</template>

<script setup lang="ts">
import { useAuthStore } from '../stores/auth'
import { useNotificationsStore } from '~/stores/notifs'
defineProps<{ title: string }>()
const auth = useAuthStore()
const notifs = useNotificationsStore()
const { toggleMobile } = useSidebar()
const router = useRouter()
const avatarMenuOpen = ref(false)
const avatarMenuRef = ref<HTMLElement | null>(null)

function settingsPath(): string {
  return auth.isInternal ? '/admin/settings' : '/client/settings'
}

function goToSettings(): void {
  avatarMenuOpen.value = false
  router.push(settingsPath())
}

function goToProfile(): void {
  avatarMenuOpen.value = false
  router.push(auth.isInternal ? '/admin/profile' : '/client/settings')
}

function logout(): void {
  avatarMenuOpen.value = false
  notifs.reset()
  auth.logout()
  router.push('/login')
}

function onPointerDown(event: MouseEvent): void {
  const target = event.target as Node | null
  if (avatarMenuRef.value && target && !avatarMenuRef.value.contains(target)) {
    avatarMenuOpen.value = false
  }
}

onMounted(() => {
  document.addEventListener('mousedown', onPointerDown)
  if (auth.isAuthenticated) {
    notifs.refreshUnreadCount()
  }
})

onBeforeUnmount(() => {
  document.removeEventListener('mousedown', onPointerDown)
})

watch(
  () => auth.isAuthenticated,
  (isAuthenticated) => {
    if (isAuthenticated) notifs.refreshUnreadCount()
    else notifs.reset()
  }
)
</script>

<style scoped>
.menu-item {
  width: 100%;
  display: flex;
  align-items: center;
  padding: 0.55rem 0.8rem;
  font-size: 0.85rem;
  font-weight: 600;
  text-align: left;
  color: var(--app-text);
}
.menu-item:hover {
  background: var(--nav-hover-bg);
}
.menu-item.danger {
  color: #ef4444;
}
</style>
