<template>
  <!-- Sidebar: ALWAYS visible — collapses to 52px icon rail, never disappears -->
  <aside
    class="fixed top-0 left-0 bottom-0 z-30 flex flex-col transition-[width] duration-[220ms] ease-[cubic-bezier(0.4,0,0.2,1)] overflow-hidden"
    :class="sidebar.isOpen.value ? 'w-[230px]' : 'w-[52px]'"
    style="background: var(--app-surface); border-right: 1px solid var(--app-border-2);"
  >

    <!-- ── Header: toggle button + logo ── -->
    <div
      class="flex items-center h-[60px] px-[10px] gap-2 shrink-0"
      style="border-bottom: 1px solid var(--app-border-2);"
    >
     

      <!-- Logo — fades out when collapsed -->
      <div
        class="flex items-center gap-2 flex-1 min-w-0 transition-[opacity,transform] duration-[180ms] ease-[cubic-bezier(0.4,0,0.2,1)]"
        :class="sidebar.isOpen.value ? 'opacity-100 translate-x-0' : 'opacity-0 -translate-x-2 pointer-events-none'"
      >
        <div class="w-7 h-7 rounded-[8px] flex items-center justify-center shrink-0 text-xs" style="background:#c8a96e">🏢</div>
        <div class="font-serif text-sm leading-tight" style="color:var(--app-text)">
          AST-FISC
          <span class="block text-[11px] italic" style="color:#c8a96e">Domiciliation</span>
        </div>
      </div>
       <!-- Claude's panel-left toggle icon — always visible -->
      <button 
        class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 transition-colors duration-150"
        style="color: var(--app-text-muted);"
        :title="sidebar.isOpen.value ? 'Réduire' : 'Développer'"
        @mouseenter="e => (e.currentTarget as HTMLElement).style.cssText += 'background:var(--app-surface-hover);color:var(--app-text)'"
        @mouseleave="e => (e.currentTarget as HTMLElement).style.cssText = 'color:var(--app-text-muted)'"
        @click="sidebar.toggle()"
      >
        <!-- Claude sidebar toggle: rect with vertical divider -->
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="3" width="18" height="18" rx="2"/>
          <path d="M9 3v18"/>
        </svg>
      </button>
    </div>

    <!-- ── User card ── -->
    <div
      v-if="auth.user"
      class="mx-[6px] my-2 rounded-xl flex items-center shrink-0 transition-all duration-[220ms] ease-[cubic-bezier(0.4,0,0.2,1)] overflow-hidden"
      :class="sidebar.isOpen.value ? 'gap-2.5 px-3 py-2.5' : 'justify-center px-[6px] py-[6px]'"
      style="background:var(--app-surface-2);border:1px solid var(--app-border);"
    >
      <div
        class="w-8 h-8 rounded-full flex items-center justify-center text-[11px] font-bold shrink-0"
        :style="`background:${auth.user.color}22;color:${auth.user.color}`"
      >{{ auth.user.avatar }}</div>
      <div
        class="min-w-0 overflow-hidden transition-[opacity,max-width] duration-[180ms]"
        :class="sidebar.isOpen.value ? 'opacity-100 max-w-[160px]' : 'opacity-0 max-w-0'"
      >
        <div class="text-xs font-bold truncate" style="color:var(--app-text)">{{ auth.user.name }}</div>
        <div class="text-[10px] font-medium" :style="`color:${auth.user.color}`">{{ roleLabel }}</div>
      </div>
    </div>

    <!-- ── Navigation ── -->
    <nav class="flex-1 overflow-y-auto overflow-x-hidden px-[6px] pb-3 [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">
      <template v-for="(item, index) in props.nav" :key="index">
        <template v-if="!item.role || auth.user?.role === item.role">

          <!-- Section label — collapses to a thin divider when closed -->
          <div
            v-if="item.section"
            class="overflow-hidden transition-all duration-[160ms]"
            :class="sidebar.isOpen.value ? 'max-h-8 pt-3 pb-1.5' : 'max-h-[1px] pt-0 pb-0 mt-2'"
          >
            <div
              class="px-2 text-[9px] font-bold uppercase tracking-[.1em] whitespace-nowrap"
              :class="sidebar.isOpen.value ? 'opacity-100' : 'opacity-0'"
              style="color:var(--app-text-faint); transition: opacity 0.15s"
            >{{ item.section }}</div>
          </div>

          <!-- Nav link -->
          <NuxtLink
            v-else-if="item.to && !item.action"
            :to="item.to"
            class="flex items-center rounded-xl mb-0.5 text-[13px] font-medium transition-all duration-150 overflow-hidden whitespace-nowrap"
            :class="[
              isActive(item.to) ? 'nav-active' : 'nav-inactive',
              sidebar.isOpen.value ? 'gap-2.5 px-2.5 py-2.5' : 'justify-center px-0 py-2'
            ]"
            @click="closeMobile"
          >
            <!-- First-letter avatar as icon when collapsed (no icon provided) -->
            <span
              class="shrink-0 w-[22px] h-[22px] flex items-center justify-center rounded text-[13px] font-bold leading-none transition-all duration-150"
              :class="sidebar.isOpen.value ? 'text-[16px]' : ''"
            >
              <template v-if="item.icon"><span v-html="item.icon" /></template>
              <template v-else>{{ (item.label ?? '?').charAt(0) }}</template>
            </span>

            <!-- Label -->
            <span
              class="flex-1 overflow-hidden transition-[opacity,max-width] duration-[160ms] ease-[cubic-bezier(0.4,0,0.2,1)]"
              :class="sidebar.isOpen.value ? 'opacity-100 max-w-[180px]' : 'opacity-0 max-w-0'"
            >{{ item.label }}</span>

            <!-- Badge -->
            <span
              v-if="item.badge && sidebar.isOpen.value"
              class="min-w-[18px] h-[18px] rounded-md text-[10px] font-black flex items-center justify-center px-1 shrink-0"
              style="background:#c8a96e;color:#13161f"
            >{{ item.badge }}</span>
          </NuxtLink>

          <!-- Action button -->
          <button
            v-else-if="item.action"
            class="flex items-center rounded-xl mb-0.5 text-[13px] font-medium w-full transition-all duration-150 nav-inactive overflow-hidden whitespace-nowrap"
            :class="sidebar.isOpen.value ? 'gap-2.5 px-2.5 py-2.5 text-left' : 'justify-center px-0 py-2'"
            @click="() => { item.action?.(); closeMobile() }"
          >
            <span
              class="shrink-0 w-[22px] h-[22px] flex items-center justify-center rounded text-[13px] font-bold leading-none transition-all duration-150"
              :style="item.highlight ? 'color:#c8a96e' : ''"
            >
              <template v-if="item.icon"><span v-html="item.icon" /></template>
              <template v-else>{{ (item.label ?? '?').charAt(0) }}</template>
            </span>
            <span
              class="overflow-hidden transition-[opacity,max-width] duration-[160ms] ease-[cubic-bezier(0.4,0,0.2,1)]"
              :class="[
                sidebar.isOpen.value ? 'opacity-100 max-w-[180px]' : 'opacity-0 max-w-0',
                item.highlight ? 'font-bold' : ''
              ]"
              :style="item.highlight ? 'color:#c8a96e' : ''"
            >{{ item.label }}</span>
          </button>

        </template>
      </template>
    </nav>

    <!-- ── Logout ── -->
    <div
      class="px-[6px] pb-4 pt-2 shrink-0"
      style="border-top:1px solid var(--app-border-2);"
    >
      <button
        class="flex items-center rounded-xl w-full text-[13px] font-medium nav-inactive transition-all duration-150 overflow-hidden whitespace-nowrap"
        :class="sidebar.isOpen.value ? 'gap-2.5 px-2.5 py-2.5 text-left' : 'justify-center px-0 py-2'"
        @click="handleLogout"
      >
        <span class="shrink-0 w-[22px] h-[22px] flex items-center justify-center">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
            <polyline points="16 17 21 12 16 7"/>
            <line x1="21" y1="12" x2="9" y2="12"/>
          </svg>
        </span>
        <span
          class="overflow-hidden transition-[opacity,max-width] duration-[160ms]"
          :class="sidebar.isOpen.value ? 'opacity-100 max-w-[180px]' : 'opacity-0 max-w-0'"
        >Déconnexion</span>
      </button>
    </div>

  </aside>
</template>

<script setup lang="ts">
import { useAuthStore } from '~/stores/auth'
import { useSidebar }   from '~/composables/useSidebar'

interface NavItem {
  label?:     string
  to?:        string
  section?:   string
  action?:    () => void
  badge?:     number | string
  highlight?: boolean
  role?:      string
  icon?:      string
}

const props   = defineProps<{ nav: NavItem[] }>()
const auth    = useAuthStore()
const sidebar = useSidebar()
const router  = useRouter()
const route   = useRoute()

const roleLabel = computed(() => ({
  admin:          'Super Admin',
  domiciliataire: 'Domiciliataire',
  client:         'Client',
}[auth.user?.role ?? ''] ?? ''))

function isActive(to: string): boolean {
  const path = to.split('?')[0]
  return route.path === path || route.path.startsWith(path + '/')
}

function closeMobile() {
  if (typeof window !== 'undefined' && window.innerWidth < 768) sidebar.close()
}

async function handleLogout(): Promise<void> {
  auth.logout()
  await router.push('/login')
}
</script>

<style scoped>
.nav-active   { background: var(--app-surface-hover); color: #c8a96e; }
.nav-inactive { color: var(--app-text-muted); }
.nav-inactive:hover { background: var(--app-surface-hover); color: var(--app-text); }
</style>