<!-- app/components/AppSidebar.vue -->
<template>
  <aside
    class="fixed top-0 left-0 bottom-0 w-[230px] flex flex-col z-30 transition-colors"
    style="background: var(--app-surface); border-right: 1px solid var(--app-border-2);"
  >
    <!-- Logo -->
    <div class="flex items-center gap-2.5 px-4 py-5 shrink-0"
         style="border-bottom: 1px solid var(--app-border-2);">
      <div class="w-8 h-8 rounded-[9px] flex items-center justify-center flex-shrink-0 text-sm"
           style="background: #c8a96e">🏢</div>
      <div class="font-serif text-sm leading-tight" style="color: var(--app-text)">
        AST-FISC
        <span class="block text-[11px] italic" style="color: #c8a96e">Domiciliation</span>
      </div>
    </div>

    <!-- User card -->
    <div
      v-if="auth.user"
      class="mx-2.5 my-3 px-3 py-2.5 rounded-xl flex items-center gap-2.5 flex-shrink-0"
      style="background: var(--app-surface-2); border: 1px solid var(--app-border);"
    >
      <div
        class="w-8 h-8 rounded-full flex items-center justify-center text-[11px] font-bold flex-shrink-0"
        :style="`background:${auth.user.color}22;color:${auth.user.color}`"
      >{{ auth.user.avatar }}</div>
      <div class="min-w-0">
        <div class="text-xs font-bold truncate" style="color: var(--app-text)">
          {{ auth.user.name }}
        </div>
        <div class="text-[10px] font-medium" :style="`color:${auth.user.color}`">
          {{ roleLabel }}
        </div>
      </div>
    </div>

    <!-- Nav -->
    <nav class="flex-1 overflow-y-auto px-2.5 pb-3">
      <template v-for="(item, index) in props.nav" :key="index">

        <div v-if="item.section"
             class="px-2 pt-3 pb-1.5 text-[9px] font-bold uppercase tracking-[.1em]"
             style="color: var(--app-text-faint)">
          {{ item.section }}
        </div>

        <NuxtLink
          v-else-if="item.to && !item.action"
          :to="item.to"
          class="flex items-center gap-2.5 px-2.5 py-2.5 rounded-xl mb-0.5 text-[13px] font-medium transition-all duration-150"
          :class="isActive(item.to) ? 'nav-active' : 'nav-inactive'"
        >
          <span class="flex-1">{{ item.label }}</span>
          <span
            v-if="item.badge"
            class="min-w-[18px] h-[18px] rounded-md text-[10px] font-black flex items-center justify-center px-1"
            style="background: #c8a96e; color: #13161f"
          >{{ item.badge }}</span>
        </NuxtLink>

        <button
          v-else-if="item.action"
          class="flex items-center gap-2.5 px-2.5 py-2.5 rounded-xl mb-0.5 text-[13px] font-medium w-full text-left transition-all duration-150 nav-inactive"
          :style="item.highlight ? 'color:#c8a96e;font-weight:700' : ''"
          @click="item.action"
        >{{ item.label }}</button>

      </template>
    </nav>

    <!-- Logout -->
    <div class="px-2.5 pb-4 pt-2 flex-shrink-0"
         style="border-top: 1px solid var(--app-border-2);">
      <button
        class="flex items-center gap-2.5 px-2.5 py-2.5 rounded-xl w-full text-[13px] font-medium nav-inactive"
        @click="handleLogout"
      >Déconnexion</button>
    </div>
  </aside>
</template>

<script setup lang="ts">
import { useAuthStore } from '~/stores/auth'

const props  = defineProps<{ nav: any[] }>()
const auth   = useAuthStore()
const router = useRouter()
const route  = useRoute()

const roleLabel = computed(() => {
  const roles: Record<string, string> = {
    admin:          'Super Admin',
    domiciliataire: 'Domiciliataire',
    client:         'Client',
  }
  return roles[auth.user?.role ?? ''] ?? ''
})

function isActive(to: string): boolean {
  const path = to.split('?')[0]
  return route.path === path || route.path.startsWith(path + '/')
}

async function handleLogout(): Promise<void> {
  auth.logout()
  await router.push('/login')
}
</script>