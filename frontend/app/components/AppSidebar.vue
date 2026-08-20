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
    class="app-sidebar fixed top-0 left-0 bottom-0 z-50 flex flex-col transition-[width,transform] duration-300 ease-in-out"
    :style="asideStyle"
  >

    <!-- ── HEADER / LOGO ── -->
    <div
      class="shrink-0 flex items-center h-14 px-3 gap-2"
      style="border-bottom: 1px solid var(--app-border-2);"
    >
      <!-- Logo mark — short code from runtime config, never a hardcoded
           brand string. Subtle inner highlight + soft shadow give it
           depth instead of reading as a flat placeholder tile. -->
      <div
        class="w-8 h-8 rounded-[9px] flex items-center justify-center shrink-0 text-[11px] font-black select-none"
        style="
          background: linear-gradient(155deg, #d8bd85 0%, #c8a96e 55%, #b8985c 100%);
          color: #171310;
          font-family: serif;
          letter-spacing: -0.5px;
          box-shadow: 0 1px 2px rgba(0,0,0,0.25), inset 0 1px 0 rgba(255,255,255,0.25);
        "
      >{{ appShortCode }}</div>

      <!-- Brand — only when expanded (desktop) or always on mobile -->
      <div
        class="flex-1 min-w-0 overflow-hidden transition-all duration-200"
        :style="showLabels ? 'opacity:1;max-width:200px' : 'opacity:0;max-width:0;pointer-events:none'"
      >
        <div class="font-serif text-sm leading-tight truncate" style="color: var(--app-text)">{{ appName }}</div>
        <!-- <div class="text-[10px] italic truncate" style="color: #c8a96e">Domiciliation</div> -->
      </div>

      <!-- Collapse toggle — custom tooltip instead of native title,
           for visual consistency with the rest of the sidebar. -->
      <button
        class="group relative hidden lg:flex w-8 h-8 rounded-lg items-center justify-center shrink-0 transition-colors nav-inactive focus-ring"
        :aria-label="isOpen ? 'Réduire la barre latérale' : 'Agrandir la barre latérale'"
        @click="toggle"
      >
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="3" width="18" height="18" rx="2"/>
          <path d="M9 3v18"/>
          <path v-if="isOpen"  d="M5 9l-2 3 2 3"/>
          <path v-if="!isOpen" d="M5 9l2 3-2 3"/>
        </svg>
        <span
          class="nav-tooltip pointer-events-none absolute left-full ml-3 top-1/2 -translate-y-1/2 px-2.5 py-1.5 rounded-lg text-xs font-medium whitespace-nowrap z-60 hidden lg:block"
          style="background: var(--app-surface-2); border: 1px solid var(--app-border); color: var(--app-text); box-shadow: 0 4px 16px rgba(0,0,0,0.18);"
        >{{ isOpen ? 'Réduire' : 'Agrandir' }}</span>
      </button>

      <!-- Mobile close button -->
      <button
        class="lg:hidden w-8 h-8 rounded-lg flex items-center justify-center shrink-0 nav-inactive focus-ring"
        aria-label="Fermer le menu"
        @click="closeMobile"
      >
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
          <path d="M18 6L6 18M6 6l12 12"/>
        </svg>
      </button>
    </div>

    <!-- ── USER CARD ── -->
    <!-- Now a real link to account settings — previously an inert div
         with no way to act on it. In collapsed mode a tooltip shows the
         full name and role, so identity is never lost when icon-only. -->
    <NuxtLink
      v-if="auth.user"
      :to="settingsPath"
      class="group relative mx-2 mt-3 mb-1 rounded-xl flex items-center shrink-0 transition-colors focus-ring"
      :class="showLabels ? 'px-3 py-2.5 gap-2.5' : 'justify-center px-0 py-2'"
      style="background: var(--app-surface-2); border: 1px solid var(--app-border);"
    >
      <UserAvatar :size="32" font-size="11px" />

      <div
        v-if="showLabels"
        class="min-w-0 flex-1 overflow-hidden"
      >
        <div class="text-xs font-bold truncate" style="color: var(--app-text)">{{ auth.user.name }}</div>
        <div class="text-[10px] font-medium truncate" :style="`color:${auth.user.color}`">{{ roleLabel }}</div>
      </div>

      <!-- Subtle affordance that this card is clickable, only shown
           once there's room for it. -->
      <svg
        v-if="showLabels"
        width="14" height="14" viewBox="0 0 24 24" fill="none"
        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
        class="shrink-0 opacity-40 group-hover:opacity-80 transition-opacity"
        style="color: var(--app-text)"
      >
        <path d="M9 18l6-6-6-6"/>
      </svg>

      <!-- Tooltip for icon-only mode — same visual treatment as nav
           item tooltips, so it reads as part of the same system. -->
      <span
        v-if="!showLabels"
        class="nav-tooltip pointer-events-none absolute left-full ml-3 px-2.5 py-1.5 rounded-lg text-xs font-medium whitespace-nowrap z-60 hidden lg:block"
        style="background: var(--app-surface-2); border: 1px solid var(--app-border); color: var(--app-text); box-shadow: 0 4px 16px rgba(0,0,0,0.18);"
      >
        <div class="font-bold" style="color: var(--app-text)">{{ auth.user.name }}</div>
        <div :style="`color:${auth.user.color}`">{{ roleLabel }}</div>
      </span>
    </NuxtLink>

    <!-- Skeleton — shown only for the brief moment before the session
         restores on first paint, so there's no layout jump once
         auth.user becomes available. -->
    <div
      v-else
      class="mx-2 mt-3 mb-1 rounded-xl flex items-center shrink-0 animate-pulse"
      :class="showLabels ? 'px-3 py-2.5 gap-2.5' : 'justify-center px-0 py-2'"
      style="background: var(--app-surface-2); border: 1px solid var(--app-border);"
    >
      <div class="w-8 h-8 rounded-full shrink-0" style="background: var(--app-border)"/>
      <div v-if="showLabels" class="min-w-0 flex-1 space-y-1.5">
        <div class="h-2.5 w-2/3 rounded-full" style="background: var(--app-border)"/>
        <div class="h-2 w-1/3 rounded-full" style="background: var(--app-border)"/>
      </div>
    </div>

    <!-- ── NAV — hidden scrollbar, fully scrollable ── -->
    
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

        <!-- NuxtLink — active item now gets a left-edge accent bar in
             addition to the existing background highlight, so "you are
             here" is legible at a glance even in icon-only mode. -->
        <NuxtLink
          v-else-if="item.to && !item.action"
          :to="item.to"
          class="group relative flex items-center rounded-xl mb-0.5 text-[13px] font-medium transition-all duration-150 focus-ring"
          :class="[
            showLabels ? 'gap-3 px-2.5 py-2' : 'justify-center px-0 py-2.5',
            isActive(item.to) ? 'nav-active' : 'nav-inactive',
          ]"
          @click="closeMobile"
        >
          <span
            v-if="isActive(item.to)"
            class="absolute left-0 top-1/2 -translate-y-1/2 w-[3px] h-5 rounded-r-full"
            style="background: #c8a96e"
          />
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
          class="group relative flex items-center rounded-xl mb-0.5 text-[13px] font-medium w-full text-left transition-all duration-150 nav-inactive focus-ring"
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
        class="group relative flex items-center rounded-xl w-full text-[13px] font-medium nav-inactive transition-all duration-150 focus-ring"
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
import { useSidebar } from '~/composables/useSidebar'
import { computed, ref, onMounted } from 'vue'

const props  = defineProps<{ nav: any[] }>()
const auth   = useAuthStore()
const router = useRouter()
const route  = useRoute()
const { isOpen, isMobileOpen, toggle, toggleMobile, closeMobile } = useSidebar()

// Product name/short code come from runtime config (nuxt.config.ts) so
// no brand string is ever hardcoded in this component.
const config       = useRuntimeConfig()
const appName      = computed(() => config.public.appName as string)
const appShortCode = computed(() =>
  (config.public.appShortCode as string) || appName.value.slice(0, 2).toUpperCase()
)

// Where the user card links to — same convention already used by
// AppTopbar.vue's avatar link, kept consistent across both.
const settingsPath = computed(() => auth.isInternal ? '/admin/profile' : '/client/settings')

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

/* Keyboard focus — visible outline for anyone navigating by keyboard.
   Previously relied on the browser default, which is hard to see
   against a dark surface and inconsistent across browsers. */
.focus-ring:focus-visible {
  outline: 2px solid #c8a96e;
  outline-offset: 2px;
  border-radius: 10px;
}

/* Backdrop transition */
.t-backdrop-enter-active { transition: opacity 0.2s ease; }
.t-backdrop-leave-active { transition: opacity 0.18s ease; }
.t-backdrop-enter-from,
.t-backdrop-leave-to     { opacity: 0; }

/* Respect the OS-level "reduce motion" preference — skip the
   width/transform/backdrop transitions entirely for anyone who's
   asked for it. */
@media (prefers-reduced-motion: reduce) {
  .app-sidebar,
  .t-backdrop-enter-active,
  .t-backdrop-leave-active {
    transition: none !important;
  }
}
</style>