<!-- app/components/AppSidebar.vue -->
<template>

  <!-- Mobile backdrop — blurs page behind, tap to close -->
  <Transition name="t-backdrop">
    <div
      v-if="isMobileOpen"
      class="fixed inset-0 z-40 lg:hidden"
      style="background: rgba(0,0,0,0.5); backdrop-filter: blur(3px);"
      @click="closeMobile"
    />
  </Transition>

  <aside
    class="fixed top-0 left-0 bottom-0 z-50 flex flex-col transition-[width,transform] duration-300 ease-in-out"
    :style="asideStyle"
  >

    <!-- ── HEADER ── -->
    <div
      class="shrink-0 flex items-center h-14 px-3 gap-2"
      style="border-bottom: 1px solid var(--app-border-2);"
    >
      <!-- Logo mark -->
      <div
        class="w-8 h-8 rounded-[9px] flex items-center justify-center shrink-0 text-[11px] font-black select-none"
        style="background: #c8a96e; color: #111; font-family: serif; letter-spacing: -0.5px;"
      >AF</div>

      <!-- Brand — only when expanded (desktop) or always on mobile -->
      <div
        class="flex-1 min-w-0 overflow-hidden transition-all duration-200"
        :style="showLabels ? 'opacity:1;max-width:200px' : 'opacity:0;max-width:0;pointer-events:none'"
      >
        <div class="font-serif text-sm leading-tight truncate" style="color: var(--app-text)">AST-FISC</div>
        <div class="text-[10px] italic truncate" style="color: #c8a96e">Domiciliation</div>
      </div>

      <button
        class="hidden lg:flex w-8 h-8 rounded-lg items-center justify-center shrink-0 transition-colors nav-inactive"
        :title="isOpen ? 'Réduire' : 'Agrandir'"
        @click="toggle"
      >
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="3" width="18" height="18" rx="2"/>
          <path d="M9 3v18"/>
          <path v-if="isOpen"  d="M5 9l-2 3 2 3"/>
          <path v-if="!isOpen" d="M5 9l2 3-2 3"/>
        </svg>
      </button>

      <!-- Mobile close button -->
      <button
        class="lg:hidden w-8 h-8 rounded-lg flex items-center justify-center shrink-0 nav-inactive"
        @click="closeMobile"
      >
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
          <path d="M18 6L6 18M6 6l12 12"/>
        </svg>
      </button>
    </div>

    <!-- ── USER CARD ── -->
    <div
      v-if="auth.user"
      class="mx-2 mt-3 mb-1 rounded-xl flex items-center shrink-0"
      :class="showLabels ? 'px-3 py-2.5 gap-2.5' : 'justify-center px-0 py-2'"
      style="background: var(--app-surface-2); border: 1px solid var(--app-border); transition: all 0.2s ease;"
    >
      <div
        class="w-8 h-8 rounded-full flex items-center justify-center text-[11px] font-bold shrink-0"
        :style="`background:${auth.user.color}22;color:${auth.user.color}`"
      >{{ auth.user.avatar }}</div>

      <div
        v-if="showLabels"
        class="min-w-0 flex-1 overflow-hidden"
      >
        <div class="text-xs font-bold truncate" style="color: var(--app-text)">{{ auth.user.name }}</div>
        <div class="text-[10px] font-medium truncate" :style="`color:${auth.user.color}`">{{ roleLabel }}</div>
      </div>
    </div>

    <!-- ── NAV — hidden scrollbar, fully scrollable ── -->
    <!--
      FIX: overflow-y-auto here means the nav scrolls independently.
      The aside has fixed top/bottom so it never clips.
      On mobile the full sidebar is always fully expanded (showLabels=true)
      so all items are always visible and reachable.
    -->
    <nav
      class="flex-1 py-1 sidebar-scroll"
      :class="showLabels ? 'overflow-y-auto px-2' : 'overflow-y-auto px-1.5'"
    >
      <template v-for="(item, idx) in props.nav" :key="idx">

        <!-- Section label -->
        <div
          v-if="item.section"
          class="pt-3 pb-1 text-[9px] font-bold uppercase tracking-[.12em] truncate transition-all"
          :class="showLabels ? 'px-2' : 'text-center opacity-0 h-0 overflow-hidden py-0'"
          style="color: var(--app-text-faint)"
        >{{ item.section }}</div>

        <!-- Divider when icon-only -->
        <div
          v-if="item.section && !showLabels"
          class="my-2 mx-1.5"
          style="border-top: 1px solid var(--app-border-2)"
        />

        <!-- NuxtLink -->
        <NuxtLink
          v-else-if="item.to && !item.action"
          :to="item.to"
          class="group relative flex items-center rounded-xl mb-0.5 text-[13px] font-medium transition-all duration-150"
          :class="[
            showLabels ? 'gap-3 px-2.5 py-2' : 'justify-center px-0 py-2.5',
            isActive(item.to) ? 'nav-active' : 'nav-inactive',
          ]"
          @click="closeMobile"
        >
          <span class="shrink-0 flex items-center justify-center w-5 h-5">
            <component :is="item.icon" />
          </span>
          <span v-if="showLabels" class="flex-1 truncate leading-none">{{ item.label }}</span>
          <span
            v-if="item.badge && showLabels"
            class="shrink-0 min-w-4.5 h-4.5 rounded-md text-[10px] font-black flex items-center justify-center px-1"
            style="background: #c8a96e; color: #13161f"
          >{{ item.badge }}</span>
          <!-- Tooltip for icon-only mode -->
          <span
            v-if="!showLabels"
            class="nav-tooltip pointer-events-none absolute left-full ml-3 px-2.5 py-1.5 rounded-lg text-xs font-medium whitespace-nowrap z-60 hidden lg:block"
            style="background: var(--app-surface-2); border: 1px solid var(--app-border); color: var(--app-text); box-shadow: 0 4px 16px rgba(0,0,0,0.18);"
          >{{ item.label }}</span>
        </NuxtLink>

        <!-- Action button -->
        <button
          v-else-if="item.action"
          class="group relative flex items-center rounded-xl mb-0.5 text-[13px] font-medium w-full text-left transition-all duration-150 nav-inactive"
          :class="showLabels ? 'gap-3 px-2.5 py-2' : 'justify-center px-0 py-2.5'"
          :style="item.highlight ? 'color:#c8a96e;font-weight:700' : ''"
          @click="() => { item.action(); closeMobile() }"
        >
          <span class="shrink-0 flex items-center justify-center w-5 h-5">
            <component :is="item.icon" />
          </span>
          <span v-if="showLabels" class="flex-1 truncate leading-none">{{ item.label }}</span>
          <span
            v-if="!showLabels"
            class="nav-tooltip pointer-events-none absolute left-full ml-3 px-2.5 py-1.5 rounded-lg text-xs font-medium whitespace-nowrap z-60 hidden lg:block"
            style="background: var(--app-surface-2); border: 1px solid var(--app-border); color: var(--app-text); box-shadow: 0 4px 16px rgba(0,0,0,0.18);"
          >{{ item.label }}</span>
        </button>

      </template>
    </nav>

    <!-- ── LOGOUT ── -->
    <div
      class="shrink-0 pt-1 pb-3"
      :class="showLabels ? 'px-2' : 'px-1.5'"
      style="border-top: 1px solid var(--app-border-2);"
    >
      <button
        class="group relative flex items-center rounded-xl w-full text-[13px] font-medium nav-inactive transition-all duration-150"
        :class="showLabels ? 'gap-3 px-2.5 py-2' : 'justify-center px-0 py-2.5'"
        @click="handleLogout"
      >
        <span class="shrink-0 flex items-center justify-center w-5 h-5">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
               stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
            <polyline points="16 17 21 12 16 7"/>
            <line x1="21" y1="12" x2="9" y2="12"/>
          </svg>
        </span>
        <span v-if="showLabels" class="flex-1 truncate leading-none">Déconnexion</span>
        <span
          v-if="!showLabels"
          class="nav-tooltip pointer-events-none absolute left-full ml-3 px-2.5 py-1.5 rounded-lg text-xs font-medium whitespace-nowrap z-60 hidden lg:block"
          style="background: var(--app-surface-2); border: 1px solid var(--app-border); color: var(--app-text); box-shadow: 0 4px 16px rgba(0,0,0,0.18);"
        >Déconnexion</span>
      </button>
    </div>

  </aside>
</template>

<script setup lang="ts">
import { useAuthStore } from '~/stores/auth'
import { computed } from 'vue'

const props  = defineProps<{ nav: any[] }>()
const auth   = useAuthStore()
const router = useRouter()
const route  = useRoute()
const { isOpen, isMobileOpen, toggle, closeMobile } = useSidebar()

// On mobile: always show full labels regardless of isOpen
// On desktop: follow isOpen state
// FIX: when on mobile and sidebar was "collapsed" on desktop,
// we should still show full labels in the mobile drawer.
const isMobile = ref(false)
onMounted(() => {
  isMobile.value = window.innerWidth < 1024
  window.addEventListener('resize', () => {
    isMobile.value = window.innerWidth < 1024
  }, { passive: true })
})

const showLabels = computed(() => isMobile.value ? true : isOpen.value)

// Sidebar width: desktop follows isOpen, mobile is always 230px
const asideStyle = computed(() => {
  if (isMobile.value) {
    // Mobile: slide in/out as overlay, always full width
    return {
      width: '230px',
      background: 'var(--app-surface)',
      borderRight: '1px solid var(--app-border-2)',
      transform: isMobileOpen.value ? 'translateX(0)' : 'translateX(-100%)',
    }
  }
  // Desktop: width transitions between expanded and collapsed
  return {
    width: isOpen.value ? '230px' : '64px',
    background: 'var(--app-surface)',
    borderRight: '1px solid var(--app-border-2)',
    transform: 'translateX(0)',
  }
})

const roleLabel = computed(() => {
  const roles: Record<string, string> = {
    admin: "Super Admin",
    domiciliataire: "Domiciliataire",
    client: "Client",
  };
  return roles[auth.user?.role ?? ""] ?? "";
});

function isActive(to: string): boolean {
  const path = to.split('?')[0]
  return route.path === path || route.path.startsWith(path + '/')
}

async function handleLogout(): Promise<void> {
  closeMobile()
  auth.logout()
  await router.push('/login')
}
</script>

<style scoped>
/* Hide scrollbar — still scrollable */
.sidebar-scroll {
  scrollbar-width: none;
  -ms-overflow-style: none;
}
.sidebar-scroll::-webkit-scrollbar { display: none; }

/* Tooltip: fade in on hover */
.nav-tooltip {
  opacity: 0;
  transform: translateX(-4px);
  transition: opacity 0.12s ease, transform 0.12s ease;
}
.group:hover .nav-tooltip {
  opacity: 1;
  transform: translateX(0);
}

/* Backdrop transition */
.t-backdrop-enter-active { transition: opacity 0.2s ease; }
.t-backdrop-leave-active { transition: opacity 0.18s ease; }
.t-backdrop-enter-from,
.t-backdrop-leave-to     { opacity: 0; }
</style>